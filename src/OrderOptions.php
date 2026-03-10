<?php

namespace Recca0120\QuickOrder;

class OrderOptions
{
    public string $description;

    public string $note;

    /** @var Customer|null */
    public $customer;

    public string $orderNumber;

    public string $status;

    public string $createdVia;

    public function __construct(
        string $description = '自訂訂單',
        string $note = '',
        ?Customer $customer = null,
        string $orderNumber = '',
        string $status = 'pending',
        string $createdVia = 'checkout'
    ) {
        $this->description = $description;
        $this->note = $note;
        $this->customer = $customer;
        $this->orderNumber = $orderNumber;
        $this->status = $status;
        $this->createdVia = $createdVia;
    }
}
