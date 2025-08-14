<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait; // <-- USE TRAIT
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Http\Response;

class AdminUtilityController extends Controller
{
    use ApiResponseTrait; // <-- INCLUDE TRAIT

    public function migrateAndSeed(Request $request)
    {
        $request->validate(['seed_organization_data' => 'sometimes|boolean', 'force' => 'sometimes|boolean']);
        $outputBuffer = new BufferedOutput(); $fullOutput = ""; $overallSuccess = true;

        try {
            Log::info('Admin API: Running migrations...');
            $exitCodeMigrate = Artisan::call('migrate', ['--force' => $request->input('force', true)], $outputBuffer);
            $fullOutput .= "Migration Output:\n" . $outputBuffer->fetch() . "\n";
            if ($exitCodeMigrate !== 0) { $overallSuccess = false; Log::error("Admin API: Migrations failed."); /* return error or continue */ }

            if ($overallSuccess) { // Only seed if migration was okay or forced to continue
                Log::info('Admin API: Running DatabaseSeeder...');
                $exitCodeDbSeed = Artisan::call('db:seed', ['--force' => true], $outputBuffer);
                $fullOutput .= "DatabaseSeeder Output:\n" . $outputBuffer->fetch() . "\n";
                if ($exitCodeDbSeed !== 0) { $overallSuccess = false; Log::error("Admin API: DatabaseSeeder failed."); }
            }

            if ($overallSuccess && $request->input('seed_organization_data') === true) {
                Log::info('Admin API: Running OrganizationTeamPlayerSeeder...');
                $exitCodeOrgSeed = Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\OrganizationTeamPlayerSeeder', '--force' => true], $outputBuffer);
                $fullOutput .= "OrganizationTeamPlayerSeeder Output:\n" . $outputBuffer->fetch() . "\n";
                if ($exitCodeOrgSeed !== 0) { $overallSuccess = false; Log::error("Admin API: OrganizationTeamPlayerSeeder failed."); }
            }

            $data = ['output' => $fullOutput];
            if ($overallSuccess) {
                return $this->successResponse($data, 'Migrations and seeding process completed.');
            } else {
                return $this->errorResponse('One or more steps failed during migrate/seed.', Response::HTTP_INTERNAL_SERVER_ERROR, $data);
            }
        } catch (\Exception $e) {
            Log::error('Admin API Error (migrateAndSeed): ' . $e->getMessage());
            return $this->errorResponse('An unexpected error occurred.', Response::HTTP_INTERNAL_SERVER_ERROR, ['details' => $e->getMessage(), 'output' => $fullOutput]);
        }
    }

    public function migrateFreshAndSeed(Request $request)
    {
        $request->validate(['seed_organization_data' => 'sometimes|boolean']);
        $outputBuffer = new BufferedOutput(); $fullOutput = ""; $overallSuccess = true;

        try {
            Log::info('Admin API: Running migrate:fresh --seed...');
            $exitCodeFresh = Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true], $outputBuffer);
            $fullOutput .= "Migrate Fresh & DatabaseSeeder Output:\n" . $outputBuffer->fetch() . "\n";
            if ($exitCodeFresh !== 0) { $overallSuccess = false; Log::error("Admin API: migrate:fresh --seed failed."); /* return error */ }

            if ($overallSuccess && $request->input('seed_organization_data') === true) {
                Log::info('Admin API: Running OrganizationTeamPlayerSeeder (post-fresh)...');
                $exitCodeOrgSeed = Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\OrganizationTeamPlayerSeeder', '--force' => true], $outputBuffer);
                $fullOutput .= "OrganizationTeamPlayerSeeder Output:\n" . $outputBuffer->fetch() . "\n";
                if ($exitCodeOrgSeed !== 0) { $overallSuccess = false; Log::error("Admin API: OrganizationTeamPlayerSeeder failed post-fresh."); }
            }

            $data = ['output' => $fullOutput];
            if ($overallSuccess) {
                return $this->successResponse($data, 'Migrate fresh and seeding process completed.');
            } else {
                return $this->errorResponse('One or more steps failed during migrate:fresh/seed.', Response::HTTP_INTERNAL_SERVER_ERROR, $data);
            }
        } catch (\Exception $e) {
            Log::error('Admin API Error (migrateFreshAndSeed): ' . $e->getMessage());
            return $this->errorResponse('An unexpected error occurred.', Response::HTTP_INTERNAL_SERVER_ERROR, ['details' => $e->getMessage(), 'output' => $fullOutput]);
        }
    }
}
