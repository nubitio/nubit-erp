<?php

namespace App\Command;

use App\Domain\Invoice\InvoiceCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'invoice:calculator',
    description: 'Invoice calculator',
)]
class InvoiceCalculatorCommand extends Command
{
    protected function configure()
    {
        $this->setHelp('Calcula valores de los comprobantes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Invoice calculator');
            $total = $io->ask('Total', 0);
            $discountPercentage = $io->ask('Discount percentage', 0);
            $taxPercentage = $io->ask('Tax percentage', 0);
            $chargesPercentage = $io->ask('Charges percentage', 0);
            $invoiceCalculator = new InvoiceCalculator(
                $total,
                $discountPercentage,
                $taxPercentage,
                $chargesPercentage
            );
            $invoiceAmounts = $invoiceCalculator->calculate();
            $io->text(
                sprintf(
                    'Subtotal: %s, Taxed amount: %s, Discount amount: %s, Charges amount: %s, Tax: %s, Total: %s',
                    $invoiceAmounts->getSubtotal(),
                    $invoiceAmounts->getTaxedAmount(),
                    $invoiceAmounts->getDiscountAmount(),
                    $invoiceAmounts->getChargesAmount(),
                    $invoiceAmounts->getTax(),
                    $invoiceAmounts->getTotal()
                )
            );
            $io->success('Success');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error');
            return Command::FAILURE;
        }
    }
}