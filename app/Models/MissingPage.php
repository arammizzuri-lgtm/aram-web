<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An address a visitor asked for that does not exist — a mistyped link, an
 * old one shared before a project was renamed, a page taken down. Counted by
 * App\Http\Middleware\RecordMissingPage; listed in the admin (Broken links).
 */
class MissingPage extends Model
{
    /** Past this many addresses, only the ones already listed are counted. */
    public const MAX_ROWS = 2000;

    protected $fillable = ['path', 'hits', 'referrer', 'first_seen_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'hits' => 'integer'];
    }

    /** The address, as a link on this site. */
    public function url(): string
    {
        return url($this->path);
    }

    /** Whether the visitor arrived from one of this site's own pages — a link the studio can fix. */
    public function fromThisSite(): bool
    {
        return filled($this->referrer) && parse_url($this->referrer, PHP_URL_HOST) === parse_url(url('/'), PHP_URL_HOST);
    }
}
