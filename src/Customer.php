<?php

namespace Recca0120\QuickOrder;

class Customer
{
    private const FIELDS = ['name', 'email', 'phone_number', 'address_1', 'city', 'postcode'];

    public $name = '';

    public $email = '';

    public $phone_number = '';

    public $address_1 = '';

    public $city = '';

    public $postcode = '';

    public static function fromArray(array $data): self
    {
        return self::create(function ($field) use ($data) {
            return $data[$field] ?? '';
        });
    }

    public static function fromPost(array $post): self
    {
        return self::create(function ($field) use ($post) {
            $raw = wp_unslash($post[$field] ?? '');

            return $field === 'email' ? sanitize_email($raw) : sanitize_text_field($raw);
        });
    }

    public static function fromRequest(\WP_REST_Request $request): self
    {
        return self::create(function ($field) use ($request) {
            return $request->get_param($field) ?? '';
        });
    }

    /** @return array{first_name: string, last_name: string} */
    public function splitName(): array
    {
        $name = $this->name;
        if ($name === '') {
            $name = strstr($this->email, '@', true) ?: '';
        }

        $parts = explode(' ', $name, 2);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';

        if ($lastName === '' && mb_strlen($firstName) > 1 && preg_match('/\p{Han}/u', $firstName)) {
            $lastName = mb_substr($firstName, 1);
            $firstName = mb_substr($firstName, 0, 1);
        }

        return ['first_name' => $firstName, 'last_name' => $lastName];
    }

    private static function create(callable $extractor): self
    {
        $customer = new self();
        foreach (self::FIELDS as $field) {
            $value = $extractor($field);
            if ($value !== '') {
                $customer->$field = $value;
            }
        }

        return $customer;
    }
}
