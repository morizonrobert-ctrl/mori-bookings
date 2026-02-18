<?php
namespace Mori;

class Admin {
    private $db;
    private $user;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->user = new User();
    }

    /**
     * Check whether a user ID is an admin (super_admin or admin)
     */
    public function isAdmin($userId)
    {
        return $this->user->isAdmin($userId);
    }

    /**
     * Convenience static check
     */
    public static function check($userId)
    {
        $inst = new self();
        return $inst->isAdmin($userId);
    }
}
