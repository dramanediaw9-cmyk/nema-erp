<?php

namespace Tests\Unit;

use App\Support\ErpStatusPresenter;
use PHPUnit\Framework\TestCase;

class ErpStatusPresenterTest extends TestCase
{
    public function test_it_normalizes_workflow_statuses(): void
    {
        $this->assertSame(
            ['label' => 'Brouillon', 'tone' => 'muted', 'class' => 'badge-muted'],
            ErpStatusPresenter::present('workflow', 'draft')
        );

        $this->assertSame(
            ['label' => 'En attente', 'tone' => 'warning', 'class' => 'badge-warning'],
            ErpStatusPresenter::present('workflow', 'pending_approval')
        );

        $this->assertSame(
            ['label' => 'Confirme', 'tone' => 'success', 'class' => 'badge-success'],
            ErpStatusPresenter::present('workflow', 'validated')
        );

        $this->assertSame(
            ['label' => 'Rejete', 'tone' => 'danger', 'class' => 'badge-danger'],
            ErpStatusPresenter::present('workflow', 'rejected')
        );
    }

    public function test_it_normalizes_payment_statuses(): void
    {
        $this->assertSame(
            ['label' => 'Paye', 'tone' => 'success', 'class' => 'badge-success'],
            ErpStatusPresenter::present('payment', 'paid')
        );

        $this->assertSame(
            ['label' => 'Partiellement paye', 'tone' => 'warning', 'class' => 'badge-warning'],
            ErpStatusPresenter::present('payment', 'partial')
        );

        $this->assertSame(
            ['label' => 'En attente', 'tone' => 'muted', 'class' => 'badge-muted'],
            ErpStatusPresenter::present('payment', 'unpaid')
        );
    }

    public function test_it_normalizes_activity_sync_and_portfolio_statuses(): void
    {
        $this->assertSame(
            ['label' => 'Actif', 'tone' => 'success', 'class' => 'badge-success'],
            ErpStatusPresenter::present('activity', true)
        );

        $this->assertSame(
            ['label' => 'Synchronise', 'tone' => 'success', 'class' => 'badge-success'],
            ErpStatusPresenter::present('sync', 'synced')
        );

        $this->assertSame(
            ['label' => 'A rapprocher', 'tone' => 'warning', 'class' => 'badge-warning'],
            ErpStatusPresenter::present('sync', 'pending_review')
        );

        $this->assertSame(
            ['label' => 'Erreur de synchronisation', 'tone' => 'danger', 'class' => 'badge-danger'],
            ErpStatusPresenter::present('sync', 'failed')
        );

        $this->assertSame(
            ['label' => 'A jour', 'tone' => 'success', 'class' => 'badge-success'],
            ErpStatusPresenter::present('portfolio', 'clear')
        );

        $this->assertSame(
            ['label' => 'En retard', 'tone' => 'warning', 'class' => 'badge-warning'],
            ErpStatusPresenter::present('portfolio', 'overdue')
        );
    }

    public function test_it_accepts_explicit_label_and_tone_for_generic_statuses(): void
    {
        $this->assertSame(
            ['label' => 'Pret a rapprocher', 'tone' => 'success', 'class' => 'badge-success'],
            ErpStatusPresenter::present('generic', null, ['label' => 'Pret a rapprocher', 'tone' => 'success'])
        );
    }
}
