<?php

namespace Gett\MyparcelBE\Factory\Consignment;

use Configuration;
use Country;
use Exception;
use Gett\MyparcelBE\Carrier\PackageTypeCalculator;
use Gett\MyparcelBE\Constant;
use Gett\MyparcelBE\OrderLabel;
use Gett\MyparcelBE\Service\Order\OrderTotalWeight;
use Gett\MyparcelBE\Service\ProductConfigurationProvider;
use Module;
use MyParcelBE;
use MyParcelNL\Sdk\src\Factory\ConsignmentFactory as ConsignmentSdkFactory;
use MyParcelNL\Sdk\src\Helper\MyParcelCollection;
use MyParcelNL\Sdk\src\Model\Consignment\AbstractConsignment;
use MyParcelNL\Sdk\src\Model\Consignment\BpostConsignment;
use MyParcelNL\Sdk\src\Model\Consignment\DPDConsignment;
use MyParcelNL\Sdk\src\Model\Consignment\PostNLConsignment;
use MyParcelNL\Sdk\src\Model\MyParcelCustomsItem;
use Tools;
use Gett\MyparcelBE\Service\CarrierConfigurationProvider;
use Order;

class ConsignmentFactory
{
    private $api_key;
    private $request;
    private $configuration;
    private $module;

    /**
     * @var AbstractConsignment
     */
    private $consignment;

    /**
     * @var array
     */
    private $orderData;

    /**
     * ConsignmentFactory constructor.
     *
     * @param string        $api_key
     * @param array         $request
     * @param Configuration $configuration
     * @param Module        $module
     */
    public function __construct(string $api_key, array $request, Configuration $configuration, Module $module)
    {
        $this->api_key       = $api_key;
        $this->configuration = $configuration;
        $this->request       = $request;
        $this->module        = $module;
    }

    /**
     * @param array $orders
     *
     * @return MyParcelCollection
     * @throws \MyParcelNL\Sdk\src\Exception\MissingFieldException
     */
    public function fromOrders(array $orders): MyParcelCollection
    {
        $myParcelCollection = (new MyParcelCollection());

        foreach ($orders as $order) {
            $this->setOrderData($order);
            $this->createConsignment();
            $myParcelCollection
                ->setUserAgents($this->getUserAgent())
                ->addConsignment($this->initConsignment());
        }

        return $myParcelCollection;
    }

    /**
     * @param array $order
     *
     * @return MyParcelCollection
     * @throws \MyParcelNL\Sdk\src\Exception\MissingFieldException
     */
    public function fromOrder(array $order): MyParcelCollection
    {
        $this->setOrderData($order);
        $this->createConsignment();

        $myParcelCollection = (new MyParcelCollection());

        for ($i = 0; $i < $this->request['label_amount']; ++$i) {
            $consignment = $this->initConsignment();
            foreach (Constant::SINGLE_LABEL_CREATION_OPTIONS as $key => $option) {
                if (isset($this->request[$key])) {
                    if (method_exists($this, $option)) {
                        $consignment = $this->{$option}($consignment);
                    }
                }
            }

            $myParcelCollection
                ->setUserAgents($this->getUserAgent())
                ->addConsignment($consignment);
        }

        return $myParcelCollection;
    }

    /**
     * @return array
     */
    private function getUserAgent(): array
    {
        return [
            'PrestaShop'            => _PS_VERSION_,
            'MyParcelBE-PrestaShop' => (new MyParcelBE())->version
        ];
    }

    /**
     * @param array $orders
     */
    private function setOrderData(array $orders): void
    {
        $this->orderData = $orders;
    }

    /**
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function setBaseData(): void
    {
        $orderObject = new Order((int)$this->orderData['id_order']);
        $floatWeight = $orderObject->getTotalWeight();
        $this->consignment
            ->setApiKey($this->api_key)
            ->setReferenceId($this->orderData['id_order'])
            ->setPackageType($this->getPackageType())
            ->setDeliveryDate($this->getDeliveryDate())
            ->setDeliveryType($this->getDeliveryType())
            ->setLabelDescription($this->getFormattedLabelDescription())
            ->setTotalWeight((new OrderTotalWeight())->convertWeightToGrams($floatWeight));
    }

    /**
     * @return int
     */
    private function getPackageType(): int
    {
        if (isset($this->request['packageType'])) {
            $packageType = $this->request['packageType'];
        } else {
            $packageType = (new PackageTypeCalculator())->getOrderPackageType($this->orderData['id_order'], $this->orderData['id_carrier']);
        }

        if (empty($carrierSettings['delivery']['packageType'][(int)$packageType])) {
            $packageType = AbstractConsignment::PACKAGE_TYPE_PACKAGE; // TODO: for NL the DPD and Bpost don't allow any.
        }

        return (int)$packageType;
    }

    /**
     * @return string|null
     */
    private function getDeliveryDate(): ?string
    {
        if (! isset($this->orderData['delivery_settings']->date)) {
            return null;
        }

        $date             = strtotime($this->orderData['delivery_settings']->date);
        $deliveryDateTime = date('Y-m-d H:i:s', $date);
        $deliveryDate     = date("Y-m-d", $date);
        $dateOfToday      = date("Y-m-d", strtotime('now'));
        $dateOfTomorrow   = date('Y-m-d H:i:s', strtotime('now +1 day'));

        if ($deliveryDate <= $dateOfToday) {
            return $dateOfTomorrow;
        }

        return $deliveryDateTime;
    }

    /**
     * @return int
     */
    private function getDeliveryType(): int
    {
        $deliveryType = AbstractConsignment::DELIVERY_TYPE_STANDARD;

        if (! empty($delivery_setting->isPickup)) {
            $deliveryType = AbstractConsignment::DELIVERY_TYPES_NAMES_IDS_MAP[AbstractConsignment::DELIVERY_TYPE_PICKUP_NAME];
        } elseif (! empty($delivery_setting->deliveryType)) {
            $deliveryType = AbstractConsignment::DELIVERY_TYPES_NAMES_IDS_MAP[$delivery_setting->deliveryType];
        }

        return $deliveryType;
    }

    /**
     * Get the label description from the Order and check the maximum number of characters.
     *
     * @return string
     */
    private function getFormattedLabelDescription(): string
    {
        $labelDescription = $this->getLabelParams($this->orderData, Configuration::get(Constant::LABEL_DESCRIPTION_CONFIGURATION_NAME));

        if (strlen($labelDescription) > Constant::ORDER_DESCRIPTION_MAX_LENGTH) {
            return substr($labelDescription, 0, 42) . "...";
        }

        return $labelDescription;
    }

    /**
     * Gets the recipient and puts its data in the consignment.
     *
     * @throws Exception
     */
    private function setRecipient(): void
    {
        $this->consignment
            ->setCountry(strtoupper($this->orderData['iso_code']))
            ->setPerson($this->orderData['person'])
            ->setFullStreet($this->orderData['full_street'])
            ->setPostalCode($this->orderData['postcode'])
            ->setCity($this->orderData['city'])
            ->setEmail($this->getEmailConfiguration())
            ->setPhone($this->getPhoneConfiguration())
            ->setSaveRecipientAddress(false);
    }

    /**
     * @return string
     */
    private function getEmailConfiguration(): string
    {
        $emailConfiguration = $this->configuration::get(Constant::SHARE_CUSTOMER_EMAIL_CONFIGURATION_NAME);

        return $emailConfiguration ? $this->orderData['email'] : '';
    }

    /**
     * @return string
     */
    private function getPhoneConfiguration(): string
    {
        $phoneConfiguration = $this->configuration::get(Constant::SHARE_CUSTOMER_PHONE_CONFIGURATION_NAME);

        return $phoneConfiguration ? $this->orderData['phone'] : '';
    }

    /**
     * Set the shipment options.
     *
     * @throws Exception
     */
    private function setShipmentOptions(): void
    {
        $this->consignment
            ->setOnlyRecipient($this->hasOnlyRecipient())
            ->setSignature($this->hasSignature())
            ->setContents(AbstractConsignment::PACKAGE_CONTENTS_COMMERCIAL_GOODS)
            ->setInvoice($this->orderData['invoice_number']);
    }

    /**
     * @throws Exception
     */
    private function hasOnlyRecipient(): bool
    {
        $issetOnlyRecipient = isset($delivery_setting->shipmentOptions->only_recipient);

        if ($this->consignment instanceof PostNLConsignment && $issetOnlyRecipient) {
            $this->consignment->setOnlyRecipient(true);
        }

        return false;
    }

    private function hasSignature(): bool
    {
        $countryCode  = strtoupper($this->orderData['iso_code']);
        $hasSignature = (! empty($delivery_setting->shipmentOptions->signature) && ! empty($carrierSettings['allowSignature'][$countryCode]));

        // Signature is required for pickup delivery type
        if ($this->consignment->getDeliveryType() === AbstractConsignment::DELIVERY_TYPE_PICKUP || $hasSignature) {
            return true;
        }

        return false;
    }

    /**
     * Set the pickup location
     */
    private function setPickupLocation(): void
    {
        if ($this->consignment->getDeliveryType() !== AbstractConsignment::DELIVERY_TYPE_PICKUP) {
            return;
        }
        $pickupLocation = $delivery_setting->pickupLocation ?? null;

        $this->consignment
            ->setPickupCountry($pickupLocation->cc)
            ->setPickupCity($pickupLocation->city)
            ->setPickupLocationName($pickupLocation->location_name)
            ->setPickupStreet($pickupLocation->street)
            ->setPickupNumber($pickupLocation->number . ($pickupLocation->number_suffix ?? ''))
            ->setPickupPostalCode($pickupLocation->postal_code)
            ->setRetailNetworkId($pickupLocation->retail_network_id)
            ->setPickupLocationCode($pickupLocation->location_code);
    }

    /**
     * Sets a customs declaration for the consignment if necessary.
     *
     * @throws \Exception
     */
    private function setCustomsDeclaration(): void
    {
        $shippingCountry         = Country::getIdZone($this->orderData['id_country']);
        $customFormConfiguration = $this->configuration::get(Constant::CUSTOMS_FORM_CONFIGURATION_NAME);

        if ($shippingCountry !== 1 && $customFormConfiguration !== 'No') {
            $this->setCustomItems();
        }
    }

    /**
     * @return void
     * @throws \MyParcelNL\Sdk\src\Exception\MissingFieldException
     * @throws \ErrorException
     */
    private function setCustomItems(): void
    {
        $products = OrderLabel::getCustomsOrderProducts($this->orderData['id_order']);
        foreach ($products as $product) {
            $product = $product->get_product();

            if (! $product) {
                continue;
            }

            $weight      = (new OrderTotalWeight())->convertWeightToGrams($product['product_weight']);
            $description = $product['product_name'];
            $itemValue   = Tools::ps_round($product['unit_price_tax_incl'] * 100);

            if (strlen($description) > Constant::ITEM_DESCRIPTION_MAX_LENGTH) {
                $description = substr_replace($description, '...', Constant::ITEM_DESCRIPTION_MAX_LENGTH - 3);
            }

            $this->consignment->addItem(
                (new MyParcelCustomsItem())
                    ->setDescription($description)
                    ->setAmount($product['product_quantity'])
                    ->setWeight($weight)
                    ->setItemValue($itemValue)
                    ->setCountry($this->getCountryOfOrigin($product['product_id']))
                    ->setClassification($this->getHsCode($product['product_id']))
            );
        }
    }

    /**
     * @param int $productId
     *
     * @return string
     */
    private function getCountryOfOrigin(int $productId): string
    {
        $productCountryOfOrigin = ProductConfigurationProvider::get($productId, Constant::CUSTOMS_ORIGIN_CONFIGURATION_NAME);
        $defaultCountryOfOrigin = $this->configuration::get(Constant::DEFAULT_CUSTOMS_ORIGIN_CONFIGURATION_NAME);

        return $productCountryOfOrigin ?? $defaultCountryOfOrigin;
    }

    /**
     * @param int $productId
     *
     * @return int
     */
    private function getHsCode(int $productId): int
    {
        $productHsCode = ProductConfigurationProvider::get($productId, Constant::CUSTOMS_CODE_CONFIGURATION_NAME);
        $defaultHsCode = $this->configuration::get(Constant::DEFAULT_CUSTOMS_CODE_CONFIGURATION_NAME);

        return (int)($productHsCode ?? $defaultHsCode);
    }

    /**
     * Create a new consignment
     *
     * @return void
     * @throws Exception
     */
    private function createConsignment(): void
    {
        $this->consignment = ConsignmentSdkFactory::createByCarrierId($this->getMyParcelCarrierId($this->orderData['id_carrier']));
    }

    /**
     * @return AbstractConsignment
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function initConsignment(): AbstractConsignment
    {
        $this->setBaseData();
        $this->setRecipient();
        $this->setShipmentOptions();
        $this->setPickupLocation();
        $this->setCustomsDeclaration();

        return $this->consignment;
    }

    /**
     * @param AbstractConsignment $consignment
     *
     * @return false|AbstractConsignment
     */
    private function MYPARCELBE_RECIPIENT_ONLY(AbstractConsignment $consignment)
    {
        if ($consignment instanceof PostNLConsignment) {
            return $this->consignment->setOnlyRecipient(true);
        }

        return false;
    }

    /**
     * @param AbstractConsignment $consignment
     *
     * @return AbstractConsignment
     */
    private function MYPARCELBE_AGE_CHECK(AbstractConsignment $consignment)
    {
        return $this->consignment->setAgeCheck(true);
    }

    /**
     * @param AbstractConsignment $consignment
     *
     * @return AbstractConsignment
     */
    private function MYPARCELBE_PACKAGE_TYPE(AbstractConsignment $consignment)
    {
        return $this->consignment->setPackageType($this->request['packageType']);
    }

    /**
     * @return AbstractConsignment
     * @throws Exception
     */
    private function MYPARCELBE_INSURANCE()
    {

        $insuranceValue = 0;
        if (isset($postValues['insuranceAmount'])) {
            if (strpos($postValues['insuranceAmount'], 'amount') !== false) {
                $insuranceValue = (int)str_replace(
                    'amount',
                    '',
                    $postValues['insuranceAmount']
                );
            } else {
                $insuranceValue = (int)$postValues['insurance-amount-custom-value'] ?? 0;
                if (empty($insuranceValue)) {
                    throw new Exception('Insurance value cannot be empty');
                }
            }
        }

        if ($this->module->isBE() && $insuranceValue > 500) {
            $this->module->controller->errors[] = $this->module->l(
                'Insurance value cannot more than € 500',
                'consignmentfactory'
            );
            throw new Exception('Insurance value cannot more than € 500');
        }
        if ($this->module->isNL() && $insuranceValue > 5000) {
            $this->module->controller->errors[] = $this->module->l(
                'Insurance value cannot more than € 5000',
                'consignmentfactory'
            );
            throw new Exception('Insurance value cannot more than € 5000');
        }

        return $this->consignment->setInsurance($insuranceValue);
    }

    /**
     * @param AbstractConsignment $consignment
     *
     * @return AbstractConsignment
     * @throws Exception
     */
    private function MYPARCELBE_RETURN_PACKAGE(AbstractConsignment $consignment): AbstractConsignment
    {
        return $this->consignment->setReturn(true);
    }

    /**
     * @param AbstractConsignment $consignment
     *
     * @return false|AbstractConsignment
     */
    private function MYPARCELBE_SIGNATURE_REQUIRED(AbstractConsignment $consignment)
    {
        if (! $consignment instanceof DPDConsignment) {
            return $this->consignment->setSignature(true);
        }

        return false;
    }

    /**
     * @param AbstractConsignment $consignment
     *
     * @return AbstractConsignment
     */
    private function MYPARCELBE_PACKAGE_FORMAT(AbstractConsignment $consignment)
    {
        return $this->consignment->setLargeFormat($this->request['packageFormat'] == 2);
    }

    /**
     * @param array  $order
     * @param string $labelParams
     * @param string $labelDefaultParam
     *
     * @return string
     */
    private function getLabelParams(array $order, string $labelParams, string $labelDefaultParam = 'id_order'): string
    {
        if (! isset($this->orderData[$labelDefaultParam])) {
            $labelDefaultParam = 'id_order';
        }

        if (empty(trim($labelParams))) {
            return $order[$labelDefaultParam];
        }

        $pattern = '/\{[a-zA-Z_]+\.[a-zA-Z_]+\}/m';

        preg_match_all($pattern, $labelParams, $matches, PREG_SET_ORDER, 0);

        $keys = [];
        if (! empty($matches)) {
            foreach ($matches as $result) {
                foreach ($result as $value) {
                    $key = trim($value, '{}');
                    $key = explode('.', $key);
                    if (count($key) === 1) {
                        $keys[$value] = $key;
                        continue;
                    }
                    if (count($key) === 2) {
                        if ($key[0] === 'order') {
                            $keys[$value] = $key[1];
                            continue;
                        }
                    }
                }
            }
        }

        if (empty($keys)) {
            return $order[$labelDefaultParam];
        }

        foreach ($keys as $index => $key) {
            if (! isset($this->orderData[$key])) {
                unset($keys[$index]);
            }
            $labelParams = str_replace($index, $order[$key], $labelParams);
        }

        return trim($labelParams);
    }

    /**
     * @param int $id_carrier
     *
     * @return int
     * @throws Exception
     */
    public function getMyParcelCarrierId(int $id_carrier): int
    {
        $carrier = new \Carrier($id_carrier);
        if (! \Validate::isLoadedObject($carrier)) {
            throw new Exception('No carrier found.');
        }

        $carrierType = CarrierConfigurationProvider::get($id_carrier, 'carrierType');

        if ($carrier->id_reference == $this->configuration::get(Constant::POSTNL_CONFIGURATION_NAME)
            || $carrierType == Constant::POSTNL_CARRIER_NAME) {
            return PostNLConsignment::CARRIER_ID;
        }

        if ($carrier->id_reference == $this->configuration::get(Constant::BPOST_CONFIGURATION_NAME)
            || $carrierType == Constant::BPOST_CARRIER_NAME) {
            return BpostConsignment::CARRIER_ID;
        }

        if ($carrier->id_reference == $this->configuration::get(Constant::DPD_CONFIGURATION_NAME)
            || $carrierType == Constant::DPD_CARRIER_NAME) {
            return DPDConsignment::CARRIER_ID;
        }

        throw new Exception('Undefined carrier');
    }
}
