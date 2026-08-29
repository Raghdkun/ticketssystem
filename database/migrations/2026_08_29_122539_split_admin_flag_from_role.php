<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administering the platform and running a venue stop being alternatives.
 *
 * `role` forced a choice: a person was a super admin or an owner, never both,
 * so an administrator who also runs a hall needed two accounts. Admin-ness
 * becomes a flag, and owning venues is simply whether you own venues.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->index()->after('email');

            // The owner tier. An owner who requires approval may draft and
            // edit freely; publishing is what an admin signs off.
            $table->boolean('requires_approval')->default(false)->after('is_super_admin');
        });

        DB::table('users')->where('role', 'super_admin')->update(['is_super_admin' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('owner')->index()->after('email');
        });

        DB::table('users')->where('is_super_admin', true)->update(['role' => 'super_admin']);
        DB::table('users')->where('is_super_admin', false)->update(['role' => 'owner']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_super_admin', 'requires_approval']);
        });
    }
};
