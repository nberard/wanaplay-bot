<?php

namespace Wanaplay\Bundle\BookerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Wanaplay\Bundle\BookerBundle\Exception\NoBookingException;

class DefaultController extends Controller
{
    const TIME_BOOKING = '21:40';
//    private $destinataires = array('berard.nicolas@gmail.com');
    private $destinataires = array('berard.nicolas@gmail.com', 'chandler8692@gmail.com');

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
//            $sToday = $this->getService()->book('20:20');
            $sToday = $this->getService()->book(self::TIME_BOOKING);
            $oMessage = \Swift_Message::newInstance()
                ->setSubject('Booking Wanaplay')
                ->setFrom('report@my.server')
                ->setTo($this->destinataires)
                ->setBody(
                    'Réservation pour le ' . $sToday . ' à ' . self::TIME_BOOKING,
                    'text/html'
                );
            $sReturn = $this->get('mailer')->send($oMessage) ? 'ok mail' : 'ko mail';
        } catch (NoBookingException $oNoBookingException) {
            $sReturn = 'ko booking';
        }

        return new Response($sReturn);
    }

    public function listAction()
    {
        $aAllSlots = $this->getService()->listAll(self::TIME_BOOKING);
        if (!empty($aAllSlots)) {
            $html = $this->render('WanaplayBookerBundle:Default:list.html.twig', array('horaires' => $aAllSlots));
            $oMessage = \Swift_Message::newInstance()
                ->setSubject('Booking Wanaplay')
                ->setFrom('report@my.server')
//                ->setTo(array('berard.nicolas@gmail.com'))
                ->setTo($this->destinataires)
                ->setBody($html->getContent(), 'text/html');
            $sReturn = $this->get('mailer')->send($oMessage) ? 'ok mail' : 'ko mail';
            $sReturn .= ' : ' . count($aAllSlots) . ' slots';
        } else {
            $sReturn = 'no slots';
        }

        return new Response($sReturn);
    }
}
