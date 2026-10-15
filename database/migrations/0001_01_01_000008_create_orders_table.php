<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pedidos, sus líneas y las comisiones que genera cada línea.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_address_id')->constrained('customer_addresses')->restrictOnDelete();
            $table->foreignId('shipping_address_id')->constrained('customer_addresses')->restrictOnDelete();
            // Agente de call-center que creó el pedido.
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            // Valores de App\Enums\OrderStatus.
            $table->string('status', 20)->default('pending_invoice')->index();

            // Pago. Nunca se guarda el número completo ni el vencimiento de la tarjeta.
            $table->string('payment_method', 20);
            $table->string('payment_reference', 50)->nullable();
            $table->string('cardholder_name')->nullable();
            $table->char('card_last_four', 4)->nullable();

            // Tasas vigentes al crear el pedido, para poder auditar los cálculos.
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('card_fee_rate', 5, 2)->default(0);
            $table->decimal('total', 12, 2);

            // Facturación.
            $table->string('invoice_tax_id', 20)->nullable();
            $table->string('invoice_number', 50)->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->foreignId('invoiced_by')->nullable()->constrained('users')->nullOnDelete();

            // Entrega.
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('received_by')->nullable();
            $table->text('delivery_notes')->nullable();

            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Valores de App\Enums\ItemOrigin.
            $table->string('origin', 30);
            $table->unsignedInteger('quantity');
            // Copia del precio y costo al momento de la venta.
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_cost', 10, 2);
            // Desglose financiero de la línea (ver App\Services\SaleCalculator).
            $table->decimal('line_total', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('card_fee_amount', 12, 2);
            $table->decimal('gross_margin', 12, 2);
            // N-ésima compra de este producto por este cliente con este médico.
            $table->unsignedInteger('purchase_number');
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            // Beneficiario; el del mensajero se asigna al despachar el pedido.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Valores de App\Enums\Role: doctor, sales_rep, call_center o courier.
            $table->string('role', 30);
            $table->decimal('rate', 5, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['user_id', 'role']);
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
