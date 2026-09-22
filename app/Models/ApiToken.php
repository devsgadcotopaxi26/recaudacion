<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una fila = una entidad bancaria/cooperativa (o el GAD), con exactamente
 * un token por ambiente. No existía como modelo Eloquent hasta ahora — el
 * resto del sistema siempre la consultó vía DB::table('api_tokens')
 * (ValidateApiToken, AuthBancaController, GenerarTokenApi). Se crea aquí
 * porque TransaccionPago::apiToken() (belongsTo) la referenciaba sin que
 * la clase existiera — la relación nunca funcionó hasta este commit.
 */
class ApiToken extends Model
{
    use HasFactory;

    protected $table = 'api_tokens';

    protected $fillable = [
        'entidad_nombre',
        'usuario',
        'password_hash',
        'token',
        'activo',
        'requests_permitidos',
        'ultimo_uso',
        'ip_permitida',
        'notas',
    ];

    protected $hidden = [
        'password_hash',
        'token',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'requests_permitidos' => 'integer',
        'ultimo_uso' => 'datetime',
    ];

    public function transaccionesPago(): HasMany
    {
        return $this->hasMany(TransaccionPago::class, 'api_token_id');
    }
}
