<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductStock extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'store_id',         // Foreign key for the store
        'batch_id',         // Foreign key for the product batch
        'quantity',         // Quantity of stock available
    ];

}
