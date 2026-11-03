<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\TableLayout;
use Illuminate\Http\Request;

class TablesInLayoutController extends Controller
{
    public function getAllTablesLayout()
    {

        $tables = TableLayout::select('id', 'number_of_chairs','x_position','y_position')->get();

        if ($tables->isEmpty()) {
            return response()->json([
                'success' => true,
                'categories' => [],
                'message' => 'لا توجد طاولات حالياً داخل النظام.'
            ]);
        }

        return response()->json([
            'message' => ['الطاولات الموجودة كالتالي'],
            'tables' => $tables
        ]);
    }

}
