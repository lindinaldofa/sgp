<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: expand enum to allow both old and new values during migration
        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending','approved','rejected','contracted','paid','overdue','cancelled') NOT NULL DEFAULT 'pending'");
        // Step 2: map old values to new
        DB::statement("UPDATE expenses SET status = 'contracted' WHERE status IN ('pending')");
        DB::statement("UPDATE expenses SET status = 'paid'       WHERE status IN ('approved')");
        DB::statement("UPDATE expenses SET status = 'cancelled'  WHERE status IN ('rejected')");
        // Step 3: narrow to new values only
        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('contracted','paid','overdue','cancelled') NOT NULL DEFAULT 'contracted'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending','approved','rejected','contracted','paid','overdue','cancelled') NOT NULL DEFAULT 'contracted'");
        DB::statement("UPDATE expenses SET status = 'pending'  WHERE status IN ('contracted','overdue')");
        DB::statement("UPDATE expenses SET status = 'approved' WHERE status IN ('paid')");
        DB::statement("UPDATE expenses SET status = 'rejected' WHERE status IN ('cancelled')");
        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
};
