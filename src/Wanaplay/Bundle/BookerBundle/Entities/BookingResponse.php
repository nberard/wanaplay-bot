<?php

namespace Wanaplay\Bundle\BookerBundle\Entities;

class BookingResponse
{
    /**
     * @var \DateTime
     */
    private $targetDate;

    /**
     * BookingResponse constructor.
     * @param \DateTime $targetDate
     */
    public function __construct(\DateTime $targetDate)
    {
        $this->targetDate = $targetDate;
    }

    /**
     * @return \DateTime
     */
    public function getTargetDate()
    {
        return $this->targetDate;
    }
}
