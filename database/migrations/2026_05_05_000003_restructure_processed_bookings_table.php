<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            // Rename reserved_at → reservation_at (date, API uses date not datetime)
            $table->renameColumn('reserved_at', 'reservation_at');

            // Replace string codes with integer IDs to match API
            $table->dropColumn(['hotel_code', 'room_type_code', 'agency_code']);
        });

        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->unsignedInteger('hotel_id')->nullable()->after('tourists');
            $table->unsignedInteger('room_type_id')->nullable()->after('hotel_id');
            $table->string('room_type_name')->nullable()->after('room_type_id');
            $table->unsignedInteger('operator_id')->nullable()->after('room_type_name');
            $table->string('operator_name')->nullable()->after('operator_id');
            $table->unsignedInteger('agency_id')->nullable()->after('departure_at');
            $table->string('guest_info')->nullable()->after('tourists');
            $table->json('tourist_ids')->nullable()->after('tourists');

            $table->integer('total_bonus')->default(0)->after('currency_code');
            $table->tinyInteger('hm_approval')->nullable()->after('total_bonus');
            $table->tinyInteger('payment_status_ag')->default(0)->after('hm_approval');
            $table->tinyInteger('payment_status_rm')->default(0)->after('payment_status_ag');
            $table->tinyInteger('payment_status_cm')->default(0)->after('payment_status_rm');

            // Rename person counts
            $table->renameColumn('adults', 'person_count_adults');
            $table->renameColumn('children', 'person_count_children');
            $table->renameColumn('infants', 'person_count_teens');
        });
    }

    public function down(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->renameColumn('reservation_at', 'reserved_at');
            $table->renameColumn('person_count_adults', 'adults');
            $table->renameColumn('person_count_children', 'children');
            $table->renameColumn('person_count_teens', 'infants');

            $table->dropColumn([
                'hotel_id', 'room_type_id', 'room_type_name',
                'operator_id', 'operator_name', 'agency_id',
                'guest_info', 'tourist_ids',
                'total_bonus', 'hm_approval',
                'payment_status_ag', 'payment_status_rm', 'payment_status_cm',
            ]);

            $table->string('hotel_code')->nullable();
            $table->string('room_type_code')->nullable();
            $table->string('agency_code')->nullable();
        });
    }
};
