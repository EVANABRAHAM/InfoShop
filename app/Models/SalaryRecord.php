<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Userstamps;

class SalaryRecord extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'employee_id',
        'salary_date',
        'basic_salary',
        'allowances',
        'deductions',
        'gross_salary',
        'net_salary',
        'salary_from',
        'created_by',
        'store_id',
        'remarks',   
        'adjusts_balance',
    ];
}
