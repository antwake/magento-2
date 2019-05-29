<?php

namespace Blockchain\Icanpay\Model;

class Icanpay extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'blockchain_icanpay';

    protected $_code = self::CODE;

    protected $_canAuthorize = true;
    protected $_canCapture = true;

    protected $redirect_uri;
 	protected $_canOrder = true;

    private $baseUrl;
    private $_url;
    private $_accessToken;
    private $_clientId;
    private $_clientSecret;
    private $_locationId;
    protected $_logger;

    protected $_supportedCurrencyCodes = array('USD');
    protected $_debugReplacePrivateDataKeys
        = ['number', 'exp_month', 'exp_year', 'cvc'];
    protected $session;

    public function __construct(\Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Framework\Session\SessionManagerInterface $coreSession,
        \Magento\Framework\UrlInterface $baseUrl,
        array $data = array()
    ) {
        $this->session = $coreSession;
        $this->baseUrl = $baseUrl;
        $this->_logger = $logger;
        parent::__construct(
            $context, $registry, $extensionFactory, $customAttributeFactory,
            $paymentData, $scopeConfig, $logger, $moduleList, $localeDate, null,
            null, $data
        );
        $this->_countryFactory = $countryFactory;

        $this->_clientId = $this->getConfigData('blockchain_client_id');
        $this->_clientSecret = $this->getConfigData('blockchain_client_secret');
        $this->_locationId = $this->getConfigData('blockchain_location_id');
        $this->_url = ($this->getConfigData('blockchain_mode') == 1) ? 'https://quickcard.herokuapp.com' : 'https://api.quickcard.me';
    }

    public function validate()
    {
        /*
         * calling parent validate function
         */
        parent::validate();
        return $this;
    }

    /**
     * Capture Payment.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            //check if payment has been authorized
            if(is_null($payment->getParentTransactionId())) {
                $this->authorize($payment, $amount);
            }

            $order = $payment->getOrder();
            $billing = $order->getBillingAddress();

            $user_name = explode(' ', $billing->getName());

            $first_name = $billing->getFirstname();
            $last_name = $billing->getLastname();
            $email = $billing->getEmail();
            $telephone = $billing->getTelephone();
            $region = $billing->getRegion();
            $city = $billing->getCity();
            $country = $this->convert_country_code_characters($billing->getCountryId());
            $zip_code = $billing->getPostcode();
            $billing_address = explode("\n", $billing->getData('street'));
            $billing_company = (!empty($billing->getCompany())) ? $billing->getCompany() : '';
            $address_1 = $billing_address[0];
            $address_2 = (isset($billing_address[1])) ? $billing_address[1] : '';

            $param = array(
                'auth_token' => $this->session->getUserAccessToken(),
                'location_id' => $this->_locationId,
                'order_id' => $order->getIncrementId(),
                'transaction_type' => 'a',
                'currency' => 'USD',
                "address" => $address_1,
                "address_2" => $address_2,
                "street" => $address_1,
                "city" => $city,
                "zip_code" => $zip_code,
                "state" => strlen($region) > 2 ? substr($region, 0, 2) : $country,
                "country" => $country,
                "billing_company" => $billing_company,
                "transaction_source" => "magento2_plugin",
                'phone_number' => preg_replace("/[^-0-9]+/", '', $telephone),
                'exp_date' => sprintf('%02d',$payment->getCcExpMonth()).substr($payment->getCcExpYear(), -2, 2),
                'card_number' => $payment->getCcNumber(),
                'card_cvv' => $payment->getCcCid(),
                'amount' => $amount,
                'email' => $email,
                'name' => $first_name . ' ' . $last_name,
                'first_name' => $first_name,
                'last_name' => $last_name
            );

            //make API request to credit card processor.
            $response = $this->makeCaptureRequest($param);

            $payment->setTransactionId(123);
            $payment->setParentTransactionId(123);

            //transaction is done.
            $payment->setIsTransactionClosed(1);
        } catch (\Exception $e) {
            $this->debug($payment->getData(), $e->getMessage());
        }

        return $this;
    }

    /**
     * Authorize a payment.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            //check if payment has been authorized
            $response = $this->makeAuthRequest();
        } catch (\Exception $e) {
            $this->debug($e->getMessage());
        }

        return $this;
    }

    /**
     * Set the payment action to authorize_and_capture
     *
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return self::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * Test method to handle an API call for authorization request.
     *
     * @param $request
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function makeAuthRequest()
    {
        $result = $this->getAccessToken();
        $this->session->setUserAccessToken($result['access_token']);

        if(!$result['access_token']) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Unauthorized'));
        }

        return $result;
    }

    /**
     * Test method to handle an API call for capture request.
     *
     * @param $request
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function makeCaptureRequest($request)
    {
        $endpoint = '/api/registrations/virtual_transaction';
        $result = $this->curl($request, $endpoint);

        if($result->success == '' || $result->success == false) {
            throw new \Magento\Framework\Exception\LocalizedException(__($result->message));
        }

        return $result;
    }

    public function getAccessToken()
    {
        $param = array(
            'client_id' => $this->_clientId,
            'client_secret' => $this->_clientSecret,
            'location_id' => $this->_locationId
        );
        $endpoint = '/oauth/token/retrieve';
        $response = $this->curl($param, $endpoint);

        return $response;
    }

    public function curl($data, $endpoint)
    {
        $url = $this->_url;

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url.''.$endpoint,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ));
        $resp = curl_exec($curl);
        curl_close($curl);
        $json = json_decode($resp, true);
        return $json;
    }

    public function getLocationId(){

        return $this->_locationId;
    }

    public function convert_country_code_characters($code){

        $arr =
            array(
                "AF" => "AFG",
                "AL" => "ALB",
                "DZ" => "DZA",
                "AS" => "ASM",
                "AD" => "AND",
                "AO" => "AGO",
                "AI" => "AIA",
                "AQ" => "ATA",
                "AG" => "ATG",
                "AR" => "ARG",
                "AM" => "ARM",
                "AW" => "ABW",
                "AU" => "AUS",
                "AT" => "AUT",
                "AZ" => "AZE",
                "BS" => "BHS",
                "BH" => "BHR",
                "BD" => "BGD",
                "BB" => "BRB",
                "BY" => "BLR",
                "BE" => "BEL",
                "BZ" => "BLZ",
                "BJ" => "BEN",
                "BM" => "BMU",
                "BT" => "BTN",
                "BO" => "BOL",
                "BQ" => "BES",
                "BA" => "BIH",
                "BW" => "BWA",
                "BV" => "BVT",
                "BR" => "BRA",
                "IO" => "IOT",
                "BN" => "BRN",
                "BG" => "BGR",
                "BF" => "BFA",
                "BI" => "BDI",
                "CV" => "CPV",
                "KH" => "KHM",
                "CM" => "CMR",
                "CA" => "CAN",
                "KY" => "CYM",
                "CF" => "CAF",
                "TD" => "TCD",
                "CL" => "CHL",
                "CN" => "CHN",
                "CX" => "CXR",
                "CC" => "CCK",
                "CO" => "COL",
                "KM" => "COM",
                "CD" => "COD",
                "CG" => "COG",
                "CK" => "COK",
                "CR" => "CRI",
                "HR" => "HRV",
                "CU" => "CUB",
                "CW" => "CUW",
                "CY" => "CYP",
                "CZ" => "CZE",
                "CI" => "CIV",
                "DK" => "DNK",
                "DJ" => "DJI",
                "DM" => "DMA",
                "DO" => "DOM",
                "EC" => "ECU",
                "EG" => "EGY",
                "SV" => "SLV",
                "GQ" => "GNQ",
                "ER" => "ERI",
                "EE" => "EST",
                "SZ" => "SWZ",
                "ET" => "ETH",
                "FK" => "FLK",
                "FO" => "FRO",
                "FJ" => "FJI",
                "FI" => "FIN",
                "FR" => "FRA",
                "GF" => "GUF",
                "PF" => "PYF",
                "TF" => "ATF",
                "GA" => "GAB",
                "GM" => "GMB",
                "GE" => "GEO",
                "DE" => "DEU",
                "GH" => "GHA",
                "GI" => "GIB",
                "GR" => "GRC",
                "GL" => "GRL",
                "GD" => "GRD",
                "GP" => "GLP",
                "GU" => "GUM",
                "GT" => "GTM",
                "GG" => "GGY",
                "GN" => "GIN",
                "GW" => "GNB",
                "GY" => "GUY",
                "HT" => "HTI",
                "HM" => "HMD",
                "VA" => "VAT",
                "HN" => "HND",
                "HK" => "HKG",
                "HU" => "HUN",
                "IS" => "ISL",
                "IN" => "IND",
                "ID" => "IDN",
                "IR" => "IRN",
                "IQ" => "IRQ",
                "IE" => "IRL",
                "IM" => "IMN",
                "IL" => "ISR",
                "IT" => "ITA",
                "JM" => "JAM",
                "JP" => "JPN",
                "JE" => "JEY",
                "JO" => "JOR",
                "KZ" => "KAZ",
                "KE" => "KEN",
                "KI" => "KIR",
                "KP" => "PRK",
                "KR" => "KOR",
                "KW" => "KWT",
                "KG" => "KGZ",
                "LA" => "LAO",
                "LV" => "LVA",
                "LB" => "LBN",
                "LS" => "LSO",
                "LR" => "LBR",
                "LY" => "LBY",
                "LI" => "LIE",
                "LT" => "LTU",
                "LU" => "LUX",
                "MO" => "MAC",
                "MK" => "MKD",
                "MG" => "MDG",
                "MW" => "MWI",
                "MY" => "MYS",
                "MV" => "MDV",
                "ML" => "MLI",
                "MT" => "MLT",
                "MH" => "MHL",
                "MQ" => "MTQ",
                "MR" => "MRT",
                "MU" => "MUS",
                "YT" => "MYT",
                "MX" => "MEX",
                "FM" => "FSM",
                "MD" => "MDA",
                "MC" => "MCO",
                "MN" => "MNG",
                "ME" => "MNE",
                "MS" => "MSR",
                "MA" => "MAR",
                "MZ" => "MOZ",
                "MM" => "MMR",
                "NA" => "NAM",
                "NR" => "NRU",
                "NP" => "NPL",
                "NL" => "NLD",
                "NC" => "NCL",
                "NZ" => "NZL",
                "NI" => "NIC",
                "NE" => "NER",
                "NG" => "NGA",
                "NU" => "NIU",
                "NF" => "NFK",
                "MP" => "MNP",
                "NO" => "NOR",
                "OM" => "OMN",
                "PK" => "PAK",
                "PW" => "PLW",
                "PS" => "PSE",
                "PA" => "PAN",
                "PG" => "PNG",
                "PY" => "PRY",
                "PE" => "PER",
                "PH" => "PHL",
                "PN" => "PCN",
                "PL" => "POL",
                "PT" => "PRT",
                "PR" => "PRI",
                "QA" => "QAT",
                "RO" => "ROU",
                "RU" => "RUS",
                "RW" => "RWA",
                "RE" => "REU",
                "BL" => "BLM",
                "SH" => "SHN",
                "KN" => "KNA",
                "LC" => "LCA",
                "MF" => "MAF",
                "PM" => "SPM",
                "VC" => "VCT",
                "WS" => "WSM",
                "SM" => "SMR",
                "ST" => "STP",
                "SA" => "SAU",
                "SN" => "SEN",
                "RS" => "SRB",
                "SC" => "SYC",
                "SL" => "SLE",
                "SG" => "SGP",
                "SX" => "SXM",
                "SK" => "SVK",
                "SI" => "SVN",
                "SB" => "SLB",
                "SO" => "SOM",
                "ZA" => "ZAF",
                "GS" => "SGS",
                "SS" => "SSD",
                "ES" => "ESP",
                "LK" => "LKA",
                "SD" => "SDN",
                "SR" => "SUR",
                "SJ" => "SJM",
                "SE" => "SWE",
                "CH" => "CHE",
                "TJ" => "TJK",
                "TZ" => "TZA",
                "TH" => "THA",
                "TL" => "TLS",
                "TG" => "TGO",
                "TK" => "TKL",
                "TO" => "TON",
                "TT" => "TTO",
                "TN" => "TUN",
                "TR" => "TUR",
                "TM" => "TKM",
                "TC" => "TCA",
                "TV" => "TUV",
                "UG" => "UGA",
                "UA" => "UKR",
                "AE" => "ARE",
                "GB" => "GBR",
                "UM" => "UMI",
                "US" => "USA",
                "UY" => "URY",
                "UZ" => "UZB",
                "VU" => "VUT",
                "VE" => "VEN",
                "VN" => "VNM",
                "VG" => "VGB",
                "VI" => "VIR",
                "WF" => "WLF",
                "EH" => "ESH",
                "YE" => "YEM",
                "ZM" => "ZMB",
                "ZW" => "ZWE",
                "AX" => "ALA"
        );

        return $arr[$code];
    }
}