<?php
/**
 * @file
 * Contains Registration Controller
 */
namespace Drupal\email_campaigner\Controller;

use Drupal\hexutils\Controller\HexController;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Render\FormattableMarkup;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use PayPal\Api\Address;
use PayPal\Api\BillingInfo;
use PayPal\Api\Cost;
use PayPal\Api\Currency;
use PayPal\Api\Invoice;
use PayPal\Api\InvoiceAddress;
use PayPal\Api\InvoiceItem;
use PayPal\Api\MerchantInfo;
use PayPal\Api\PaymentTerm;
use PayPal\Api\Phone;
use PayPal\Api\ShippingInfo;

use Drupal\email_campaigner\API\Campaign\EmailSender;

class CampaignCron extends HexController {

    public function __construct(){
        parent::__construct();
    }

    public function CampaignPaypalAPICron(){
        \Drupal::service('page_cache_kill_switch')->trigger();
        $content = '';

        $apiContext = new ApiContext(
            new OAuthTokenCredential(
                '',
                ''
            )
        );
    
        // Comment this line out and uncomment the PP_CONFIG_PATH
        // 'define' block if you want to use static file
        // based configuration
    
        $apiContext->setConfig(
            array(
                'mode' => 'live',
                'log.LogEnabled' => true,
                'log.FileName' => '../PayPal.log',
                'log.LogLevel' => 'INFO', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
                'cache.enabled' => true,
                //'cache.FileName' => '/PaypalCache' // for determining paypal cache directory
                // 'http.CURLOPT_CONNECTTIMEOUT' => 30
                // 'http.headers.PayPal-Partner-Attribution-Id' => '123123123'
                //'log.AdapterFactory' => '\PayPal\Log\DefaultLogFactory' // Factory class implementing \PayPal\Log\PayPalLogFactory
            )
        );

        $invoice = new Invoice();

// ### Invoice Info
// Fill in all the information that is
// required for invoice APIs
        $invoice
            ->setMerchantInfo(new MerchantInfo())
            ->setBillingInfo(array(new BillingInfo()))
            ->setNote("Medical Invoice 16 Jul, 2013 PST")
            ->setPaymentTerm(new PaymentTerm())
            ->setShippingInfo(new ShippingInfo());

        // ### Merchant Info
        // A resource representing merchant information that can be
        // used to identify merchant
        $invoice->getMerchantInfo()
            ->setEmail("")
            ->setFirstName("")
            ->setLastName("")
            ->setbusinessName("")
            ->setPhone(new Phone())
            ->setAddress(new Address());

        $invoice->getMerchantInfo()->getPhone()
            ->setCountryCode("")
            ->setNationalNumber("");

        // ### Address Information
        // The address used for creating the invoice
        $invoice->getMerchantInfo()->getAddress()
            ->setLine1("")
            ->setCity("")
            ->setState("")
            ->setPostalCode("")
            ->setCountryCode("");

        // ### Billing Information
        // Set the email address for each billing
        $billing = $invoice->getBillingInfo();
        $billing[0]
            ->setEmail("");

        $billing[0]->setBusinessName("")
            ->setAdditionalInfo("")
            ->setAddress(new InvoiceAddress());

        $billing[0]->getAddress()
            ->setLine1("1234 Main St.")
            ->setCity("Portland")
            ->setState("OR")
            ->setPostalCode("97217")
            ->setCountryCode("US");

        // ### Items List
        // You could provide the list of all items for
        // detailed breakdown of invoice
        $items = array();
        $items[0] = new InvoiceItem();
        $items[0]
            ->setName("Sutures")
            ->setQuantity(100)
            ->setUnitPrice(new Currency());

        $items[0]->getUnitPrice()
            ->setCurrency("USD")
            ->setValue(10);

        #### Tax Item
        // // You could provide Tax information to each item.
        // $tax = new \PayPal\Api\Tax();
        // $tax->setPercent(1)->setName("Local Tax on Sutures");
        // $items[0]->setTax($tax);

        // // Second Item
        // $items[1] = new InvoiceItem();
        // // Lets add some discount to this item.
        // $item1discount = new Cost();
        // $item1discount->setPercent("3");
        // $items[1]
        //     ->setName("Injection")
        //     ->setQuantity(5)
        //     ->setDiscount($item1discount)
        //     ->setUnitPrice(new Currency());

        // $items[1]->getUnitPrice()
        //     ->setCurrency("USD")
        //     ->setValue(5);

        #### Tax Item
        // You could provide Tax information to each item.
        // $tax2 = new \PayPal\Api\Tax();
        // $tax2->setPercent(3)->setName("Local Tax on Injection");
        // $items[1]->setTax($tax2);

        // $invoice->setItems($items);

        // #### Final Discount
        // You can add final discount to the invoice as shown below. You could either use "percent" or "value" when providing the discount
        // $cost = new Cost();
        // $cost->setPercent("2");
        // $invoice->setDiscount($cost);

        $invoice->getPaymentTerm()
            ->setTermType("DUE_ON_RECEIPT");

        // // ### Shipping Information
        // $invoice->getShippingInfo()
        //     ->setFirstName("Sally")
        //     ->setLastName("Patient")
        //     ->setBusinessName("Not applicable")
        //     ->setPhone(new Phone())
        //     ->setAddress(new InvoiceAddress());

        // $invoice->getShippingInfo()->getPhone()
        //     ->setCountryCode("001")
        //     ->setNationalNumber("5039871234");

        // $invoice->getShippingInfo()->getAddress()
        //     ->setLine1("1234 Main St.")
        //     ->setCity("Portland")
        //     ->setState("WB")
        //     ->setPostalCode("743259")
        //     ->setCountryCode("IN");

        // ### Logo
        // You can set the logo in the invoice by providing the external URL pointing to a logo
        $invoice->setLogoUrl('https://www.paypalobjects.com/webstatic/i/logo/rebrand/ppcom.svg');

        // For Sample Purposes Only.
        $request = clone $invoice;

        try {
            // ### Create Invoice
            // Create an invoice by calling the invoice->create() method
            // with a valid ApiContext (See bootstrap.php for more on `ApiContext`)
            // $invoice->create($apiContext);
            // $content .= print_r($invoice->getId(), 1);
            // $sendStatus = $invoice->send($apiContext);
            // $content .= $sendStatus;
        } catch (Exception $ex) {
            // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
            // ResultPrinter::printError("Create Invoice", "Invoice", null, $request, $ex);
            // exit(1);
            $content = $ex->getMessage();
        }

        // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
        // ResultPrinter::printResult("Create Invoice", "Invoice", $invoice->getId(), $request, $invoice);

        // return $invoice;

    

        $response = new Response(
            $content,
            Response::HTTP_OK,
            array('content-type' => 'text/html')
          );
          
        return $response;
    }

    public function CampaignContentPreview($campaign_id){
        \Drupal::service('page_cache_kill_switch')->trigger();
        $content = '';

        if(!empty($campaign_id)){
            
            $emailSender = new EmailSender($campaign_id);
            $emailContent = $emailSender->getPreapredLead(true);

            if(!empty($emailContent)){
                $content = "<div class='subject'>".$emailContent['subject']."</div>".$emailContent['html'];
            }
        }

        $response = new Response(
            $content,
            Response::HTTP_OK,
            array('content-type' => 'text/html')
          );
          
          return $response;
    }

    public function CampaignEmailAPICron(){
	$this->setLog('Emailer Cron Running');

        $activeDays = constant(date('D'));
        $activeDays = !empty($activeDays) ? $activeDays : 0;


        $this->setLog("Campaign Email API Cron is Running");

        $returnResponse = [];

        $activeCampaigns = $this->dbQuery('SELECT * FROM `email_campaign` c left join email_campaign__time ct using(campaign_id)
                where c.status = 1 and ct.active = 1 and active_days & :active_days and TIME(NOW()) between ct.starttime and ct.endtime',
                [':active_days'=> $activeDays]
            )->fetchAll();

        $returnResponse['dayOfWeek'] = date('D');
        $returnResponse['activeDays'] = $activeDays;
        $returnResponse['activeCampaigns'] = $activeCampaigns;
        $returnResponse['campaignMailer'] = [];
        

        foreach($activeCampaigns as $campaign){
            $emailerClass = $campaign->class;
            if(!empty($emailerClass)){
                try{
                    //$emailerClass = "Drupal\\email_campaigner\\API\\Campaign\\CampaignEmailer";
                    //$contentClass = "Drupal\\email_campaigner\\API\\Campaign\\EmailContent";
                    //$campaignMailer = new $emailerClass($campaign);
                    //$campaignContent = new $contentClass($campaign);
                    //$returnResponse['campaignMailer'][$campaign->campaign_id]['accounts'] = $campaignMailer->fetchAvailableEmailAccount();
                    // $campaignMailer->createSMTPConnection();
                    // $returnResponse['campaignMailer'][$campaign->campaign_id]['test_response'] = $campaignMailer->sendEmail('mail.biswas.arnab@gmail.com', 'Arnab Biswas', '', 'Shopsmeade', 'Test SMTP Mail2', 'This is a Test SMTP EMail');
                    // $returnResponse['campaignMailer'][$campaign->campaign_id]['smtp_connections'] = $campaignMailer->getSMTPConnection();

                    $emailSender = new EmailSender($campaign->campaign_id);
                    $lead = $emailSender->sendPreparedLead();
                    while($lead){
                        // $returnResponse['campaignMailer'][$campaign->campaign_id]['leads'][] = $lead;
                       $lead = $emailSender->sendPreparedLead();
                   }
                    //$returnResponse['campaignMailer'][$campaign->campaign_id]['leadsCount'] = count($returnResponse['campaignMailer'][$campaign->campaign_id]['leads']);

                    
                } catch (\Exception $e){
                    $returnResponse['campaignMailer'][$campaign->campaign_id] = $e->getMessage();
                    $this->setLog($e->getMessage());
                }
            }
        }
        // \Drupal::messenger()->addMessage(print_r($results,1));
        
        return new JsonResponse($returnResponse);
    }

}