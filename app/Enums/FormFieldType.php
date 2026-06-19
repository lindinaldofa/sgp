<?php

declare(strict_types=1);

namespace App\Enums;

enum FormFieldType: string
{
    case Text      = 'text';
    case Email     = 'email';
    case Phone     = 'phone';
    case Whatsapp  = 'whatsapp';
    case Instagram = 'instagram';
    case Tiktok    = 'tiktok';
    case Facebook  = 'facebook';
    case Twitter   = 'twitter';
    case Cpf       = 'cpf';
    case ZipCode   = 'zip_code';
    case Date      = 'date';
    case Number    = 'number';
    case Textarea  = 'textarea';
    case Select    = 'select';
    case Radio     = 'radio';
    case Checkbox  = 'checkbox';
    case Title     = 'title';
    case Paragraph = 'paragraph';

    public function label(): string
    {
        return match ($this) {
            self::Text      => 'Texto curto',
            self::Email     => 'E-mail',
            self::Phone     => 'Telefone',
            self::Whatsapp  => 'WhatsApp',
            self::Instagram => 'Instagram',
            self::Tiktok    => 'TikTok',
            self::Facebook  => 'Facebook',
            self::Twitter   => 'X (Twitter)',
            self::Cpf       => 'CPF',
            self::ZipCode   => 'CEP',
            self::Date      => 'Data',
            self::Number    => 'Número',
            self::Textarea  => 'Texto longo',
            self::Select    => 'Lista suspensa',
            self::Radio     => 'Múltipla escolha',
            self::Checkbox  => 'Caixa de seleção',
            self::Title     => 'Título',
            self::Paragraph => 'Parágrafo',
        };
    }

    public function isLayout(): bool
    {
        return in_array($this, [self::Title, self::Paragraph]);
    }

    /** Contact field this type maps to by default (null = custom field) */
    public function defaultContactMapping(): ?string
    {
        return match ($this) {
            self::Email    => 'email',
            self::Phone    => 'phone',
            self::Whatsapp  => 'whatsapp',
            self::Instagram => 'instagram',
            self::Tiktok    => 'tiktok',
            self::Facebook  => 'facebook',
            self::Twitter   => 'twitter',
            self::Cpf       => 'cpf',
            self::ZipCode  => 'zip_code',
            self::Date     => null,
            default        => null,
        };
    }
}
