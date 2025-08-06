<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->boolean('subscribed_to_newsletter')->default(true)->after('unsubscribed_at');
            $table->boolean('subscribed_to_product_updates')->default(true)->after('subscribed_to_newsletter');
            $table->boolean('subscribed_to_promotions')->default(true)->after('subscribed_to_product_updates');
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropColumn(['subscribed_to_newsletter', 'subscribed_to_product_updates', 'subscribed_to_promotions']);
        });
    }
};