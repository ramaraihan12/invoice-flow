<?php

namespace App\Invoices;

final readonly class Flag
{
    /**
     * @param  list<string>  $fields  form fields the reviewer should look at
     */
    public function __construct(
        public FlagCode $code,
        public string $message,
        public array $fields = [],
    ) {}

    /**
     * @param  array{code: string, message: string, fields?: list<string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(FlagCode::from($data['code']), $data['message'], $data['fields'] ?? []);
    }

    /**
     * @return array{code: string, message: string, fields: list<string>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'message' => $this->message,
            'fields' => $this->fields,
        ];
    }
}
