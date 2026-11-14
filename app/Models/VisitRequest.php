<?php

namespace App\Models;

use App\Support\SiteText;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A visit asked for on the website (/visit): a studio visit, a site visit or
 * a phone call, on the day and time the visitor would like. It waits in the
 * admin; the studio confirms it, or suggests another day and time (set_on,
 * set_time), and sends the WhatsApp message made ready in their language.
 */
class VisitRequest extends Model
{
    public const KINDS = ['studio' => 'Studio visit', 'site' => 'Site visit', 'call' => 'Phone call'];

    public const WAITING = 'waiting';

    public const CONFIRMED = 'confirmed';

    public const SUGGESTED = 'suggested';

    protected $fillable = [
        'name', 'phone', 'kind', 'about', 'wanted_on', 'wanted_time', 'lang', 'status', 'set_on', 'set_time', 'ip_address',
    ];

    protected $attributes = [
        'status' => self::WAITING,
        'lang' => 'en',
    ];

    protected $casts = [
        'wanted_on' => 'date',
        'set_on' => 'date',
    ];

    /** The day agreed or suggested, else the one they asked for. */
    public function day(): CarbonInterface
    {
        return $this->set_on ?? $this->wanted_on;
    }

    public function time(): string
    {
        return $this->set_time ?? $this->wanted_time;
    }

    /**
     * WhatsApp, with the message ready in their language: confirmed, or
     * another day and time suggested. Null without a usable number.
     */
    public function whatsappUrl(): ?string
    {
        $digits = phone_digits($this->phone);
        if (! $digits) {
            return null;
        }
        $lang = in_array($this->lang, ['ku', 'ar'], true) ? $this->lang : 'en';
        $text = fn (string $key) => [SiteText::class, $lang]($key);
        $message = str_replace(
            ['{name}', '{kind}', '{day}', '{time}'],
            [$this->name, $text('visit_kind_'.$this->kind), site_date($this->day(), $lang), $this->time()],
            $text($this->status === self::SUGGESTED ? 'visit_wa_suggested' : 'visit_wa_confirmed'),
        );

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }
}
