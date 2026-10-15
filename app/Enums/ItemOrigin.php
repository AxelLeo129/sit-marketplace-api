<?php

namespace App\Enums;

/**
 * Origen de un producto dentro de un pedido.
 */
enum ItemOrigin: string
{
    /** El médico lo incluyó en la receta. */
    case Prescribed = 'prescribed';

    /** El call-center lo agregó durante la llamada. */
    case AddedByCallCenter = 'added_by_call_center';
}
