<?php

namespace Wanaplay\Bundle\BookerBundle\Services;


use Guzzle\Http\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use \DateTime;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;
use Wanaplay\Bundle\BookerBundle\Entities\BookingResponse;
use Wanaplay\Bundle\BookerBundle\Entities\UserInfos;
use Wanaplay\Bundle\BookerBundle\Exception\AuthentFailedException;
use Wanaplay\Bundle\BookerBundle\Exception\NoBookingException;

class BookerService
{
    const BASE_URL = 'http://fr.wanaplay.com';
    const TOLERANCE = 0;
    const SUCCESS_AUTHENT_REDIRECT = 'http://fr.wanaplay.com/auth/infos';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Crawler
     */
    private $crawler;

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
     * @throws AuthentFailedException
     */
    private $logger;

    public function __construct($username, $password)
    {
        $this->client = new Client(self::BASE_URL);
        $cookieJar = new ArrayCookieJar();
        $cookiePlugin = new CookiePlugin($cookieJar);
        $this->client->addSubscriber($cookiePlugin);
        $response = $this->client->post(
            'auth/doLogin',
            array(),
            array(
                'sha1mdp' => sha1($password),
                'login' => $username,
                'passwd' => '',
                'commit' => 'S',
            )
        )->send();
        $redirectUrl = $response->getEffectiveUrl();
        if ($redirectUrl != self::SUCCESS_AUTHENT_REDIRECT) {
            throw new AuthentFailedException();
        }
        $this->crawler = new Crawler();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param $sTimeBooking
     * @return array
     */
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
            $this->crawler->addHtmlContent($response->getBody(true));
            $dMomentTarget = \DateTime::createFromFormat('H:i', $sTimeBooking);
            $aSlots = $this->crawler->filter('td.creneauLibre')->reduce(
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

    /**
     * @param $sTimeBooking
     * @return array
     */
    private function getAvailableReservations($sTimeBooking, $sTargetDate)
    {
        $logger = $this->logger;
        $logger->debug('target date is: ' . $sTargetDate);
        $response = $this->client->post('reservation/planning2', array(), array('date' => $sTargetDate))->send();
        $this->crawler->addHtmlContent($response->getBody(true));
        $aSlots = $this->crawler->filter('.timeSlotTime')->reduce(
            function (Crawler $node, $i) use ($sTimeBooking) {
                return $node->text() == $sTimeBooking;
            }
        );
        $aIds = $aSlots->each(
            function (Crawler $node, $i) use ($logger) {
                $sOnClick = $node->parents()->getNode(0)->getAttribute('onclick');
                $logger->info('onclick attribute: ' . var_export($sOnClick, true));
                if (!empty($sOnClick)) {
                    list(, $iIdBooking) = explode('idTspl=', $sOnClick);

                    return $iIdBooking;
                }
            }
        );
        $logger->debug('found ids: ' . var_export($aIds, true));
        return array_filter($aIds);
    }

    private function getUserInfosFromReservation($reservationId)
    {
        $response = $this->client->post('reservation/takeReservationShow?idTspl=' . $reservationId)->send();
        $this->crawler->addHtmlContent($response->getBody(true));
        $userInfos = $this->crawler->filter('select#users_0 option');
        return new UserInfos($userInfos->attr('value'), $userInfos->text());
    }

    /**
     * @param $sTimeBooking
     * @return BookingResponse
     * @throws NoBookingException
     */
    public function book($sTimeBooking)
    {
        $logger = $this->logger;
        $dToday = new DateTime();
        $sTargetDate = $dToday->modify(sprintf('+%d day', 14))->format('Y-m-d');
        $logger->info(
            sprintf(
                'checking book for time: %s ; starting at %s (local time)',
                $sTimeBooking,
                $dToday->format('d/m/Y H:i:s')
            )
        );
        $aValidIds = $this->getAvailableReservations($sTimeBooking, $sTargetDate);
        if(empty($aValidIds)) {
            throw new NoBookingException();
        }
        $validId = $aValidIds[0];
        $userInfosEntity = $this->getUserInfosFromReservation($validId);

        foreach ($aValidIds as $iId) {
            $aParams = array(
                'idTspl' => $iId,
                'date' => $sTargetDate,
                'time' => $sTimeBooking . ':00',
                'duration' => '40',
                'nb_consecutive_reservations' => '1',
                'tab_users_id_0' => $userInfosEntity->getId(),
                'tab_users_name_0' => $userInfosEntity->getFullName(),
                'nb_participants' => '1',
                'commit' => 'Confirmer',
            );
            $responseBooking = $this->client->post('reservation/takeReservationBase', array(), $aParams)->send();
            $targetDate = \DateTime::createFromFormat('Y-m-d H:i', $sTargetDate . ' ' . $sTimeBooking);
            if ($responseBooking->getStatusCode() != 200) {
                $logger->info('booked failed at: ' . $targetDate->format('Y-m-d H:i'));
                continue;
            }
            $logger->info('booked successfully at: ' . $targetDate->format('Y-m-d H:i'));
            $responseBooking = new BookingResponse($targetDate);
            return $responseBooking;
        }
        throw new NoBookingException();
    }
} 