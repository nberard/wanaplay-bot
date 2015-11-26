<?php

namespace Wanaplay\Bundle\BookerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wanaplay\Bundle\BookerBundle\Entities\BookingResponse;
use Wanaplay\Bundle\BookerBundle\Exception\NoBookingException;

class BookerCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('wanaplay:wanaplay_bot:book')
            ->setDescription('Book for a specific time')
            ->addArgument(
                'time',
                InputArgument::OPTIONAL,
                'Book at this time',
                '21:40'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $arguments = $input->getArguments();
        try {
            //in case error authent => https://accounts.google.com/UnlockCaptcha
            // (source was http://stackoverflow.com/questions/3547388/php-swift-mailer-failed-to-authenticate-on-smtp-using-2-possible-authenticators)
            $bookingResponse = $this->getContainer()->get('wanaplay_booker.service')->book($arguments['time']);
            $oMessage = \Swift_Message::newInstance()
                ->setSubject('Booking Wanaplay')
                ->setFrom($this->getContainer()->getParameter('mailer_user'))
                ->setTo($this->getContainer()->getParameter('mailing_list'))
                ->setBody(
                    'Réservation pour le ' . $bookingResponse->getTargetDate()->format('d/m/Y H:i'),
                    'text/html'
                );
            $numberOfEmailSent = $this->getContainer()->get('mailer')->send($oMessage);
            $output->writeln(sprintf('booking success and sent to %d people', $numberOfEmailSent));
        } catch (NoBookingException $oNoBookingException) {
            $output->writeln('booking failed');
        } catch (\Exception $e) {
            var_dump(get_class($e));
            var_dump($e->getTraceAsString());
        }
    }
}
