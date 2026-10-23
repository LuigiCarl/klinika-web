<?php

namespace App\Models;

use Database\Factories\AdministrativeAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministrativeAudit extends Model
{
    /** @use HasFactory<AdministrativeAuditFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'auditable_type', 'auditable_id', 'action', 'before', 'after', 'reason'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
