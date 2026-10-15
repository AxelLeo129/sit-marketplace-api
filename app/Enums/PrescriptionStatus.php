<?php

namespace App\Enums;

/**
 * Estado de una receta dentro del flujo del call-center.
 *
 * El estado de entrega ya no se duplica aquí: una vez convertida en pedido,
 * el avance (facturación, ruta, entrega) se consulta en el pedido.
 */
enum PrescriptionStatus: string
{
    /** Recién emitida por el médico, pendiente de llamada. */
    case Pending = 'pending';

    /** El paciente pidió que se le llame en otra fecha (`callback_at`). */
    case Rescheduled = 'rescheduled';

    /** El call-center ya generó un pedido a partir de la receta. */
    case Ordered = 'ordered';

    /** El paciente no desea el pedido. */
    case Cancelled = 'cancelled';

    /**
     * Nombre legible del estado.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Nueva',
            self::Rescheduled => 'Reagendada',
            self::Ordered => 'Con pedido',
            self::Cancelled => 'Cancelada',
        };
    }
}
