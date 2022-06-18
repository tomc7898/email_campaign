<?php
/**
 * @file
 * Contains Registration Controller
 */
namespace Drupal\email_campaigner\Controller;

use Drupal\hexutils\Controller\HexController;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\email_campaigner\API\Email\EmailTracker;
use Symfony\Component\HttpFoundation\Response;

class DashboardStatistics{
    protected $database;

    public function __construct(){
        $this->database = \Drupal::database();
    }

    public function EmailSentCount($lastDays = 7, $offsetDays = 0){
        //SELECT SUM(mail_count) AS total_email_sent FROM email_campaign__accounts_log al WHERE al.date >= DATE(NOW() - INTERVAL 7 DAY)
        return $this->database->query(
            "SELECT SUM(mail_count) AS total_email_sent FROM email_campaign__accounts_log al 
            WHERE al.date >= DATE(NOW() - INTERVAL :lastDays DAY) AND al.date <= DATE(NOW() - INTERVAL :offsetDays DAY)",
            [':lastDays' => $lastDays, ':offsetDays' => $offsetDays],
        )->fetchField();
    }

    public function SiteVisitor($lastDays = 7, $offsetDays = 0){
        //SELECT SUM(mail_count) AS total_email_sent FROM email_campaign__accounts_log al WHERE al.date >= DATE(NOW() - INTERVAL 7 DAY)
        return $this->database->query(
            "SELECT count(*) AS total_visitor FROM email_campaign__message_view_log mvl 
            WHERE message_id <> -1 AND mvl.dates >= DATE(NOW() - INTERVAL :lastDays DAY) AND mvl.dates <= DATE(NOW() - INTERVAL :offsetDays DAY)",
            [':lastDays' => $lastDays, ':offsetDays' => $offsetDays],
        )->fetchField();
    }

    public function EmailAccountStats(){
        $result = $this->database->query('SELECT count(active) as active, count(*) as total FROM drupaldb.email_campaign__accounts;')
        ->fetch();
        return "$result->active / $result->total";
    }

    public function DailyEmailSentLoad(){
        return $this->database->query(
            "SELECT sum(max_daily_email_count) as total_email_daily_count FROM drupaldb.email_campaign__accounts where active = 1;"
            )->fetchField();
    }

    public function HourlyEmailSentLoad(){
        return $this->database->query(
            "SELECT TRIM(sum((60/min_time_diff)*max_mail_per_batch))+0 as hourly_email_count FROM drupaldb.email_campaign__accounts where active = 1;"
            )->fetchField();
    }

    public function MailDeliveryPercentage(){
        return $this->database->query(
            "SELECT round((sum(mail_sent)/count(*))*100,2) as success_percentage FROM drupaldb.email_campaign__lead_campaign where date(timestamp_log) = current_date;"
            )->fetchField();
    }
}

class DashBoardCards extends HTMLCards{

    protected $title = '';
    protected $statNumber = '';
    protected $stats = '';

    static public function initiate(){
        return new self;
    }

    public function setTitle($title){
        $this->title = '
            <div class="d-flex fw-bold small mb-3">
                <span class="flex-grow-1">'.$title.'</span>
            </div>
        ';
        return $this;
    }

    public function setNumber($statNumber){
        $this->statNumber = '
        <div class="row align-items-center mb-2">
            <div class="col-7">
                <h3 class="mb-0">'.$statNumber.'</h3>
            </div>
            <div class="col-5">

            </div>
        </div>
        ';
        return $this;
    }

    public function setStats($stats = []){
        $this->stats = '
        <div class="small text-white text-opacity-50 text-truncate">
            <i class="fa fa-chevron-up fa-fw me-1"></i> 33.3% more than last week<br>
            <i class="far fa-user fa-fw me-1"></i> 45.5% new visitors<br>
            <i class="far fa-times-circle fa-fw me-1"></i> 3.25% bounce rate
        </div>';
        $this->stats = '';
        return $this;
    }

    public function generate(){
        $this->setBody($this->title.$this->statNumber.$this->stats);
        return parent::generate();
    }

}


class HTMLCards{

    public function setBody($body){
        $this->body = $body;
        return $this;
    }


    public function generate(){
        return '
            <div class="card mb-3">
                <div class="card-body">
                    '.$this->body.'
                </div>
                <div class="card-arrow">
                    <div class="card-arrow-top-left"></div>
                    <div class="card-arrow-top-right"></div>
                    <div class="card-arrow-bottom-left"></div>
                    <div class="card-arrow-bottom-right"></div>
                </div>
            </div>
        ';
    }
}

class CampaignHome extends HexController {

    public function __construct(){
        parent::__construct();
    }

    public function campaignHome(){
        $query = $this->database->query("SELECT name, path FROM router WHERE name LIKE :name", [":name" => 'email_campaign%']);
        $results = $query->fetchAll();
        $routes = [];

        $availableRoutes = ['email.campaign.acccount.add','email.campaign.acccount.list','email.campaign.create',
        'email.campaign.list','email.campaign.upload.lead'];

        // $routes[] = [
        //     '#markup' => "<div>Showing all Available Routes (".count($results).")</div>"
        // ];
        $routerList = [];
        foreach ($results as $id => $result) {
            $routeName = $result->name;
            /** @var $route \Symfony\Component\Routing\Route */

            if(in_array($routeName, $availableRoutes)){
                $route = \Drupal::service('router.route_provider')->getRouteByName($routeName);
            
                $routerList[] = "<div class=\"col d-grid gap-2 mx-auto\"><a class=\"btn btn-outline-secondary\" href='{$route->getPath()}'>{$route->getDefault('_title')}</a></div>";
            }
        }

        $routes[] = [
            '#markup' => ' <div class="row g-2">'.implode('',$routerList).'</div>'
        ];

        $dashboardStats = new DashboardStatistics();

        $routes[]['#markup'] ='<div class="row">
            <div class="col-xl-3 col-lg-6 g-3">
            '.DashBoardCards::initiate()->setTitle('EMAIL SENT')->setNumber($dashboardStats->EmailSentCount(2,-1))->setStats()->generate().'
            </div>
            <div class="col-xl-3 col-lg-6 g-3">
            '.DashBoardCards::initiate()->setTitle('SITE VISITORS')->setNumber($dashboardStats->SiteVisitor(2,-1))->setStats()->generate().'
            </div>
            <div class="col-xl-3 col-lg-6 g-3">
            '.DashBoardCards::initiate()->setTitle('EMAIL ACCOUNTS')->setNumber($dashboardStats->EmailAccountStats())->setStats()->generate().'
            </div>
            <div class="col-xl-3 col-lg-6 g-3">
            '.DashBoardCards::initiate()->setTitle('DAILY EMAIL VOLUME')->setNumber($dashboardStats->DailyEmailSentLoad())->setStats()->generate().'
            </div>
        </div>
        
        <div class="row">
            <div class="col-xl-3 col-lg-6 g-3">
            '.DashBoardCards::initiate()->setTitle('HOURLY EMAIL LOAD')->setNumber($dashboardStats->HourlyEmailSentLoad())->setStats()->generate().'
            </div>
            <div class="col-xl-3 col-lg-6 g-3">
            '.DashBoardCards::initiate()->setTitle('MAIL DELIVERY RATE')->setNumber($dashboardStats->MailDeliveryPercentage()."%")->setStats()->generate().'
            </div>
        </div>';
        return $routes;
    }

    public function imagePixie($image)
    {
        \Drupal::logger('controlpanel')->notice('Pixie - ' . $image);
        $tracker = new EmailTracker(null, null);
        $tracker->setImageId($image);
        return new Response(
            $tracker->serveImage(),
            Response::HTTP_OK,
            ['content-type' => 'image/png']
        );
    }

    public function access(AccountInterface $account)
    {
        // $session = \Drupal::request()->getSession();
        // $org_id = $session->get('org_id');
        // return AccessResult::allowedIf(!empty($org_id));
        return AccessResult::allowedIf($account->isAuthenticated());
    }
    
    public function accessTrue(AccountInterface $account)
    {
        return AccessResult::allowedIf(true);
    }
    
}