<?php

namespace Wanaplay\Bundle\BookerBundle\Services;


use Guzzle\Http\Client;
use Symfony\Component\DomCrawler\Crawler;
use \DateTime;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;
use Wanaplay\Bundle\BookerBundle\Exception\NoBookingException;

class BookerService {
    const BASE_URL = 'http://fr.wanaplay.com';

    /**
     * @var Client
     */
    private $client;

    public function __construct()
    {
        $this->client = new Client(self::BASE_URL);
    }

    public function book($sTimeBooking)
    {
        $cookieJar = new ArrayCookieJar();
        $cookiePlugin = new CookiePlugin($cookieJar);
        $this->client->addSubscriber($cookiePlugin);
        $this->client->post('auth/doLogin', array(),
            array(
                'sha1mdp' => sha1('password'),
                'login' => 'login',
                'passwd' => '',
                'commit' => 'S',
            )
        )->send();
        $dToday = new DateTime();
        $sToday = $dToday->modify(sprintf('+%d day', 14))->format('Y-m-d');
        $response = $this->client->post('reservation/planning2', array(), array('date' => $sToday))->send();
        $crawler = new Crawler($response->getBody(true));
        $aSlots = $crawler->filter('.timeSlotTime')->reduce(function (Crawler $node, $i) use($sTimeBooking) {
            return $node->text() == $sTimeBooking;
        });
        $aIds = array();
        $aIds = $aSlots->each(function (Crawler $node, $i) {
            $sOnClick = $node->parents()->getNode(0)->getAttribute('onclick');
            if(!empty($sOnClick)) {
                list($sPrefix, $iIdBooking) = explode('idTspl=', $sOnClick);
                return $iIdBooking;
            }
        });
        $aValidIds = array_filter($aIds);
        foreach($aValidIds as $iId) {
            $aParams = array(
                'idTspl' => $iId,
                'date' => $sToday,
                'time' => $sTimeBooking.':00',
                'duration' => '40',
                'nb_consecutive_reservations' => '1',
                'tab_users_id_0' => 'userId',
                'tab_users_name_0' => 'userFullName',
                'nb_participants' => '1',
                'commit' => 'Confirmer',
            );
            $responseBooking = $this->client->post('reservation/takeReservationBase', array(), $aParams)->send();
            if($responseBooking->getStatusCode() == 200) {
                return $sToday;
            }
        }
        throw new NoBookingException();
    }
} 