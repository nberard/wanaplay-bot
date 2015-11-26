<?php

namespace Wanaplay\Bundle\BookerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Wanaplay\Bundle\BookerBundle\Exception\NoBookingException;

class DefaultController extends Controller
{
//    const TIME_BOOKING = '17:00';
    const TIME_BOOKING = '21:40';

    /**
     * @return \Wanaplay\Bundle\BookerBundle\Services\BookerService
     */
    private function getService()
    {
        return $this->get('wanaplay_booker.service');
    }

    public function listAction()
    {
        $aAllSlots = $this->getService()->listAll(self::TIME_BOOKING);
        if (!empty($aAllSlots)) {
            $html = $this->render('WanaplayBookerBundle:Default:list.html.twig', array('horaires' => $aAllSlots));
        } else {
            $html = 'no slots';
        }

        return new Response($html);
    }
}
