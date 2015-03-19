<?php

namespace Wanaplay\Bundle\BookerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Wanaplay\Bundle\BookerBundle\Exception\NoBookingException;

class DefaultController extends Controller
{
//    const TIME_BOOKING = '17:40';
    const TIME_BOOKING = '21:40';
//    const TIME_BOOKING = '23:00';

    /**
     * @return \Wanaplay\Bundle\BookerBundle\Services\BookerService
     */
    private function getService()
    {
        return $this->get('wanaplay_booker.service');
    }

    public function bookAction()
    {
        try {
            $sToday = $this->getService()->book(self::TIME_BOOKING);
            $oMessage = \Swift_Message::newInstance()
                                      ->setSubject('Booking Wanaplay')
                                      ->setFrom('report@my.server')
                                      ->setTo(array('berard.nicolas@gmail.com', 'chandler8692@gmail.com'))
                                      ->setBody('Réservation pour le '.$sToday.' à '.self::TIME_BOOKING,
                                                'text/html');
            $sReturn = $this->get('mailer')->send($oMessage) ? 'ok mail' : 'ko mail';
        }
        catch(NoBookingException $oNoBookingException) {
            $sReturn = 'ko booking';
        }
        return new Response($sReturn);
    }
}
