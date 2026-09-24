<?php
// app/Exceptions/FbrRejectedException.php
namespace App\Exceptions;

class FbrRejectedException extends \RuntimeException
{
    public function __construct(string $message, public ?string $fbrCode = null, public array $fbrResponse = [])
    {
        parent::__construct($message);
    }
}