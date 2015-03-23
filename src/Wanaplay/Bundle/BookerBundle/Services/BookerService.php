<?php

namespace Wanaplay\Bundle\BookerBundle\Services;


use Guzzle\Http\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use \DateTime;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;
use Wanaplay\Bundle\BookerBundle\Exception\NoBookingException;

class BookerService
{
    const BASE_URL = 'http://fr.wanaplay.com';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct()
    {
        $this->client = new Client(self::BASE_URL);
        $cookieJar = new ArrayCookieJar();
        $cookiePlugin = new CookiePlugin($cookieJar);
        $this->client->addSubscriber($cookiePlugin);
        $this->client->post(
            'auth/doLogin',
            array(),
            array(
                'sha1mdp' => sha1('password'),
                'login' => 'login',
                'passwd' => '',
                'commit' => 'S',
            )
        )->send();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        error_log("set logger");
        $this->logger = $logger;
    }

    public function listAll($sTimeBooking)
    {
        $this->logger->debug('test');
        $dToday = new DateTime();
        $aAllSlots = array();
        for ($iNbJours = 0; $iNbJours <= 14; $iNbJours++) {
            $sTargetDate = $dToday->modify(sprintf('+%d day', $iNbJours))->format('Y-m-d');
            $response = $this->client->post('reservation/planning2', array(), array('date' => $sTargetDate))->send();
            $crawler = new Crawler($response->getBody(true));
            $dMomentTarget = \DateTime::createFromFormat('H:i', $sTimeBooking);
            $aSlots = $crawler->filter('td.creneauLibre')->reduce(
                function (Crawler $node, $i) use ($dMomentTarget) {
                    $dDate = \DateTime::createFromFormat('H:i', $node->text());
                    $iDiffMinutes = ($dDate->getTimestamp() - $dMomentTarget->getTimestamp()) / 60;
//                    return $iDiffMinutes >= 0 && $iDiffMinutes >= 40;
                    return $iDiffMinutes == 0;
                }
            );
            if ($aSlots->count() > 0) {
                $aTimes = $aSlots->each(
                    function (Crawler $node, $i) {
                        return $node->children()->text();
                    }
                );
                $aAllSlots[] = array('date' => $sTargetDate, 'time' => implode(', ', array_unique($aTimes)));
            }
        }

        return $aAllSlots;
    }

    public function book($sTimeBooking)
    {
        $dToday = new DateTime();
        $sTargetDate = $dToday->modify(sprintf('+%d day', 14))->format('Y-m-d');
        $response = $this->client->post('reservation/planning2', array(), array('date' => $sTargetDate))->send();
        $crawler = new Crawler($response->getBody(true));
        $aSlots = $crawler->filter('.timeSlotTime')->reduce(
            function (Crawler $node, $i) use ($sTimeBooking) {
                return $node->text() == $sTimeBooking;
            }
        );
        $aIds = $aSlots->each(
            function (Crawler $node, $i) {
                $sOnClick = $node->parents()->getNode(0)->getAttribute('onclick');
                if (!empty($sOnClick)) {
                    list($sPrefix, $iIdBooking) = explode('idTspl=', $sOnClick);

                    return $iIdBooking;
                }
            }
        );
        $aValidIds = array_filter($aIds);
        foreach ($aValidIds as $iId) {
            $aParams = array(
                'idTspl' => $iId,
                'date' => $sTargetDate,
                'time' => $sTimeBooking . ':00',
                'duration' => '40',
                'nb_consecutive_reservations' => '1',
                'tab_users_id_0' => 'userId',
                'tab_users_name_0' => 'userFullName',
                'nb_participants' => '1',
                'commit' => 'Confirmer',
            );
            $responseBooking = $this->client->post('reservation/takeReservationBase', array(), $aParams)->send();
            if ($responseBooking->getStatusCode() == 200) {
                return $sTargetDate;
            }
        }
        throw new NoBookingException();
    }
} 