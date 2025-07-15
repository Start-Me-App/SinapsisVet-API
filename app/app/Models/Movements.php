<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movements extends Model
{
    use HasFactory;

    protected $table = 'movements';

    protected $fillable = [
        'amount',
        'currency',
        'period',
        'description',
        'course_id',
        'account_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => 'integer',
    ];

    // Mapeo de IDs de moneda
    const CURRENCIES = [
        1 => 'USD',
        2 => 'ARS'
    ];

    protected $hidden = [];

    /**
     * Obtener el monto formateado
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ' . $this->getCurrencyNameAttribute();
    }

    /**
     * Obtener el nombre de la moneda
     */
    public function getCurrencyNameAttribute()
    {
        return self::CURRENCIES[$this->currency] ?? 'Unknown';
    }

    /**
     * Obtener todas las monedas disponibles
     */
    public static function getAvailableCurrencies()
    {
        return self::CURRENCIES;
    }

    /**
     * Scope para filtrar por período (formato mm/yyyy)
     */
    public function scopeByPeriod($query, $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Scope para filtrar por año
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('period', 'like', '%/' . $year);
    }

    /**
     * Scope para filtrar por mes
     */
    public function scopeByMonth($query, $month)
    {
        return $query->where('period', 'like', str_pad($month, 2, '0', STR_PAD_LEFT) . '/%');
    }

    /**
     * Scope para filtrar por moneda (por ID)
     */
    public function scopeByCurrency($query, $currencyId)
    {
        return $query->where('currency', $currencyId);
    }

    /**
     * Scope para filtrar por curso
     */
    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * Scope para filtrar por cuenta
     */
    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Relación con Courses
     */
    public function course()
    {
        return $this->belongsTo(\App\Models\Courses::class, 'course_id');
    }

    public function account()
    {
        return $this->belongsTo(\App\Models\Cuentas::class, 'account_id');
    }
} 