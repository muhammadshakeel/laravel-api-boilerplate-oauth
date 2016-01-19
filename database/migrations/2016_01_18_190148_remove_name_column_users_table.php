<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use App\Enums\AuthType;

class RemoveNameColumnUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('auth_type', AuthType::toArray())->nullable()->after('remember_token');
            $table->string('vendor_auth_token')->nullable()->after('auth_type');
            $table->text('vendor_auth_data')->nullable()->after('vendor_auth_token');

            $table->timestamp('activated_at')->nullable()->after('vendor_auth_data');
            $table->softDeletes();

            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->dropColumn(['auth_type', 'vendor_auth_token', 'vendor_auth_data', 'activated_at', 'deleted_at']);
        });
    }
}
