<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tier', 'interval', 'price_cents', 'paystack_plan_code'])]
class PlatformBillingPlan extends Model {}
