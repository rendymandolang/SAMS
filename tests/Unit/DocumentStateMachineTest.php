<?php

namespace Tests\Unit;

use App\Support\DocumentStateMachine;
use PHPUnit\Framework\TestCase;

class DocumentStateMachineTest extends TestCase
{
    public function test_document_state_transitions_are_explicit_and_terminal_states_are_closed(): void
    {
        $this->assertTrue(DocumentStateMachine::allows('purchase_request', 'draft', 'submitted'));
        $this->assertTrue(DocumentStateMachine::allows('purchase_request', 'submitted', 'approved'));
        $this->assertTrue(DocumentStateMachine::allows('purchase_request', 'submitted', 'rejected'));
        $this->assertTrue(DocumentStateMachine::allows('purchase_order', 'approved', 'partially_received'));
        $this->assertTrue(DocumentStateMachine::allows('goods_receipt', 'draft', 'posted'));
        $this->assertTrue(DocumentStateMachine::allows('stock_opname', 'draft', 'posted'));

        $this->assertFalse(DocumentStateMachine::allows('purchase_request', 'approved', 'submitted'));
        $this->assertFalse(DocumentStateMachine::allows('goods_receipt', 'posted', 'posted'));
        $this->assertFalse(DocumentStateMachine::allows('stock_opname', 'posted', 'posted'));
        $this->assertFalse(DocumentStateMachine::allows('unknown', 'draft', 'posted'));
    }
}
