<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;


class GeneTypePatterns extends Model
{
    //     protected $connection = 'kireimo_mysql';
    protected $table = 'gene_type_patterns';

    //public $timestamps = false;
    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';

    /**
     * 遺伝子関連情報取得
     *
     * @param string $adcode adcode
     * @return Adcode
     */
    public function getGeneInfo(int $sugarRiskId, int $proteinRiskd, int $fatRiskId) {
        return $this
        ->join('gene_types', 'gene_types.id', '=', 'gene_type_patterns.gene_type_id')
        ->where('sugar_risk_id', $sugarRiskId)
        ->where('protein_risk_id', $proteinRiskd)
        ->where('fat_risk_id', $fatRiskId)
        ->select('gene_type_patterns.*', 'gene_types.*')
        ->first();
    }

}
