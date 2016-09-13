<?php

namespace Wanaplay\Bundle\BookerBundle\Entities;

class UserInfos
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $fullName;

    /**
     * UserInfos constructor.
     * @param string $id
     * @param string $fullName
     */
    public function __construct($id, $fullName)
    {
        $this->id = $id;
        $this->fullName = $fullName;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getFullName()
    {
        return $this->fullName;
    }
}
