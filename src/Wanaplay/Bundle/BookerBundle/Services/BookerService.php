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

    const TOLERANCE = 0;

    /**
     * @var Client
     */
    private $client;

    private $excludedDays = array(
//        'Mon',
//        'Tue',
//        'Wed',
//        'Thu',
        'Fri',
//        'Sat',
//        'Sun',
    );

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
        $this->logger = $logger;
    }

    public function listAll($sTimeBooking)
    {
        $logger = $this->logger;
        $logger->info('listing all empty slots for ' . $sTimeBooking);
        $dToday = new DateTime();
        $aAllSlots = array();
        for ($iNbJours = 0; $iNbJours <= 14; $iNbJours++) {
            $dTargetDate = $dToday->modify(sprintf('+%d day', 1));
            $sDayOfWeek = date('D', $dTargetDate->getTimestamp());
            if (in_array($sDayOfWeek, $this->excludedDays)) {
                continue;
            }
            $sTargetDate = $dTargetDate->format('Y-m-d');
            $logger->debug('doing target date ' . $sTargetDate);
            $response = $this->client->post('reservation/planning2', array(), array('date' => $sTargetDate))->send();
            $crawler = new Crawler($response->getBody(true));
            $dMomentTarget = \DateTime::createFromFormat('H:i', $sTimeBooking);
            $aSlots = $crawler->filter('td.creneauLibre')->reduce(
                function (Crawler $node, $i) use ($dMomentTarget, $logger) {
                    $dDate = \DateTime::createFromFormat('H:i', $node->text());
                    $iDiffMinutes = ($dDate->getTimestamp() - $dMomentTarget->getTimestamp()) / 60;
//                    $logger->debug('diff is ' . $iDiffMinutes . ' mins');
//                    return $iDiffMinutes >= 0 && $iDiffMinutes >= 40;
                    return abs($iDiffMinutes) <= self::TOLERANCE;
                }
            );
            $logger->info(sprintf('found %d slots', $aSlots->count()));
            if ($aSlots->count() > 0) {
                $aTimes = $aSlots->each(
                    function (Crawler $node, $i) {
                        return $node->children()->text();
                    }
                );
                $logger->debug(sprintf('adding times %s for date %s', $sTargetDate, $aTimes));
                $aAllSlots[] = array('date' => $sTargetDate, 'time' => implode(', ', array_unique($aTimes)));
            }
        }

        return $aAllSlots;
    }

    public function book($sTimeBooking)
    {
        $logger = $this->logger;
        $dToday = new DateTime();
        $logger->info(
            sprintf(
                'checking book for time: %s ; starting at %s (local time)',
                $sTimeBooking,
                $dToday->format('d/m/Y H:i:s')
            )
        );
        $sTargetDate = $dToday->modify(sprintf('+%d day', 14))->format('Y-m-d');
        $logger->debug('target date is: ' . $sTargetDate);
        $response = $this->client->post('reservation/planning2', array(), array('date' => $sTargetDate))->send();
        $crawler = new Crawler($response->getBody(true));
        $aSlots = $crawler->filter('.timeSlotTime')->reduce(
            function (Crawler $node, $i) use ($sTimeBooking) {
                return $node->text() == $sTimeBooking;
            }
        );
        $aIds = $aSlots->each(
            function (Crawler $node, $i) use ($logger) {
                $sOnClick = $node->parents()->getNode(0)->getAttribute('onclick');
                $logger->info('onclick attribute: ' . var_export($sOnClick, true));
                if (!empty($sOnClick)) {
                    list($sPrefix, $iIdBooking) = explode('idTspl=', $sOnClick);

                    return $iIdBooking;
                }
            }
        );
        $logger->debug('found ids: ' . var_export($aIds, true));
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
                $logger->info('booked successfully: ' . $sTimeBooking . ' at date ' . $sTargetDate);

                return $sTargetDate;
            } else {
                $logger->info('booked failed: ' . $sTimeBooking . ' at date ' . $sTargetDate);
            }
        }
        throw new NoBookingException();
    }
} 