<?php
// app/Exceptions/InsufficientStockException.php
namespace App\Exceptions;

class InsufficientStockException extends \RuntimeException
{
    public function __construct(public string $productName)
    {
        parent::__construct("Insufficient stock for product: {$productName}");
    }
}