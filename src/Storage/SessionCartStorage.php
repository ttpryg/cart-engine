<?php

namespace Ttpryg\CartEngine\Storage;

use Ttpryg\CartEngine\Contracts\CartStorageInterface;
use Ttpryg\CartEngine\Entities\Cart;

class SessionCartStorage implements CartStorageInterface
{
    private string $sessionKey;

    public function __construct(string $sessionKey = '__cart_engine')
    {
        $this->sessionKey = $sessionKey;
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    public function get(string $cartId): ?Cart
    {
        if (isset($_SESSION[$this->sessionKey][$cartId]) && $_SESSION[$this->sessionKey][$cartId] instanceof Cart) {
            return $_SESSION[$this->sessionKey][$cartId];
        }

        return null;
    }

    public function save(Cart $cart): bool
    {
        $_SESSION[$this->sessionKey][$cart->getId()] = $cart;
        return true;
    }

    public function delete(string $cartId): bool
    {
        unset($_SESSION[$this->sessionKey][$cartId]);
        return true;
    }

    public function exists(string $cartId): bool
    {
        return isset($_SESSION[$this->sessionKey][$cartId]);
    }
}
