<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('customer_property_id')->nullable()->after('customer_id')->constrained('customer_properties')->nullOnDelete();
        });

        DB::table('assignments')->orderBy('id')->chunkById(100, function ($assignments): void {
            foreach ($assignments as $assignment) {
                if ($assignment->company_id === null) {
                    continue;
                }

                $customerQuery = DB::table('customers')
                    ->where('company_id', $assignment->company_id)
                    ->where('name', $assignment->customer_name);

                if (is_string($assignment->customer_email) && $assignment->customer_email !== '') {
                    $customerQuery->whereRaw('LOWER(email) = ?', [mb_strtolower($assignment->customer_email)]);
                } else {
                    // A shared name is not enough to safely merge customers when no email exists.
                    $customerQuery = null;
                }

                $customer = $customerQuery?->first();
                $customerId = $customer?->id ?? DB::table('customers')->insertGetId([
                    'company_id' => $assignment->company_id,
                    'name' => $assignment->customer_name,
                    'email' => $assignment->customer_email,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $property = DB::table('customer_properties')
                    ->where('customer_id', $customerId)
                    ->where('address', $assignment->address)
                    ->first();
                $propertyId = $property?->id ?? DB::table('customer_properties')->insertGetId([
                    'company_id' => $assignment->company_id,
                    'customer_id' => $customerId,
                    'label' => null,
                    'address' => $assignment->address,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('assignments')->where('id', $assignment->id)->update([
                    'customer_id' => $customerId,
                    'customer_property_id' => $propertyId,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_property_id');
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
