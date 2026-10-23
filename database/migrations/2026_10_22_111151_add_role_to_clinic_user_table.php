<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clinic_user', function (Blueprint $table) {
            $table->enum('role', ['administrator', 'receptionist', 'practitioner', 'patient'])
                ->default('patient')
                ->after('user_id');
            $table->index(['role', 'user_id']);
        });

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('UPDATE clinic_user INNER JOIN users ON users.id = clinic_user.user_id SET clinic_user.role = users.role');
        } elseif ($driver === 'pgsql') {
            DB::statement('UPDATE clinic_user SET role = users.role FROM users WHERE users.id = clinic_user.user_id');
        } else {
            DB::statement('UPDATE clinic_user SET role = (SELECT users.role FROM users WHERE users.id = clinic_user.user_id)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinic_user', function (Blueprint $table) {
            $table->dropIndex(['role', 'user_id']);
            $table->dropColumn('role');
        });
    }
};
