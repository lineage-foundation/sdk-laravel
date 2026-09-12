<?php

declare(strict_types=1);

namespace Lineage\Console\Traits;

use App\Models\User;
use Lineage\Models\LineageWallet;
use Lineage\Models\LineageKeypair;
use Exception;

trait UserWallets
{
    public function promptForNonEmptyString(string $question, string $default = null): string
    {
        do {
            $string = $this->ask($question, $default);
            if(!$string) {
                $this->error('This value cannot be empty');
            }
        } while (!$string);

        return $string;
    }

    public function openWallet(string $question = null, bool $closeExisting = false): ?LineageWallet
    {
        if($closeExisting === true || !\Lineage::getActiveWallet()) {
            $user = $this->findUserByEmail($question);

            try {
                $wallet = $this->walletSelect($user);
            } catch (Exception $e) {
                $this->error($e->getMessage());
                return null;
            }

            $this->openUserWallet($wallet);
        }

        return \Lineage::getActiveWallet();
    }

    public function assetsSelect(): array
    {
        $balance = \Lineage::fetchBalance();
        $assets = collect(['tokens' => $balance['total']['tokens']]);

        foreach($balance['total']['items'] as $name => $qty) {
            $assets->put($name, $qty);
        }

        $assetName = $this->choice(
            'Please select assets to send',
            $assets->keys()->toArray(),
            0,
            $maxAttempts = null,
            $allowMultipleSelections = false
        );

        $qtyAvailable = $assets[$assetName];

        do {
            $qtyToSend = $this->ask("How many '$assetName' are you sending (you have $qtyAvailable available)?");

            if(is_numeric($qtyToSend) && is_integer((int) $qtyToSend) && $qtyToSend > 0 && $qtyToSend <= $qtyAvailable) {
                $return = [
                    'name' => $assetName,
                    'qty' => (int) $qtyToSend
                ];
            } else {
                $this->error("Please enter an integer less than or equal to the available number");
            }
        } while (!isset($return));

        return $return;
    }

    private function findUserByEmail(string $question = null): User
    {
        do {
            $email = $this->promptForNonEmptyString($question ?? "What is the user's email address?");
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->error('User not found, please try again');
            }
        } while (!!$user === false);

        return $user;
    }

    private function walletSelect(User $user): LineageWallet
    {
        $wallets = $user->lineageWallets()->orderBy('default', 'DESC')->get();

        if (!$wallets->count()) {
            throw new \Exception("User does not have any wallets");
        }

        $walletName = $this->choice(
            'Which wallet are we opening?',
            $wallets->map(fn($item) => $item->name)->toArray(),
            0,
            $maxAttempts = null,
            $allowMultipleSelections = false
        );

        return $wallets->where('name', $walletName)->first();
    }

    public function keypairSelect(LineageWallet $wallet, string $question = null): LineageKeypair
    {
        $keypairs = $wallet->keypairs()->orderBy('created_at', 'DESC')->get();

        if (!$keypairs->count()) {
            throw new \Exception("The chosen wallet does not have any keypairs");
        }

        $keypairName = $this->choice(
            $question ?? 'Which keypair is this item for?',
            $keypairs->map(fn($item) => $item->name)->toArray(),
            0,
            $maxAttempts = null,
            $allowMultipleSelections = false
        );

        return $keypairs->where('name', $keypairName)->first();
    }

    private function openUserWallet(LineageWallet $wallet)
    {
        do {
            $passPhrase = $this->promptForNonEmptyString('Please enter the pass phrase for this wallet', 'passphrase');

            try {
                $walletOpened = \Lineage::setActive($wallet, $passPhrase);
            } catch (Exception $e) {
                $this->error("Could not open this wallet");
            }
        } while (!isset($walletOpened));
    }
}
