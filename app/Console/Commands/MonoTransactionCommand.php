<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;
class MonoTransactionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:mono-transaction-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Artisan::command('sync:transactions', function () {
        $users = User::whereNotNull('mono_account_id')->get();

            foreach ($users as $user) {
                $response = Http::withToken(env('MONO_SECRET_KEY'))
                    ->get("https://api.withmono.com/accounts/{$user->mono_account_id}/transactions");

                $transactions = $response->json()['data'];

                foreach ($transactions as $txn) {
                    $user->transactions()->updateOrCreate(
                        ['reference' => $txn['id']],
                        [
                            'amount' => $txn['amount'],
                            'type' => $txn['type'],
                            'description' => $txn['narration'],
                            'date' => $txn['date'],
                        ]
                    );
                }
            }

            $this->info('Transactions synced successfully!');
        });
    }
}
