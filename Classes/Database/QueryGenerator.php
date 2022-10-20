<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Database;

class QueryGenerator extends \TYPO3\CMS\Core\Database\QueryGenerator
{
    public $allowedTables = ['tt_address','fe_users'];

    /**
     * Build a dropdown box. override function from parent class. Limit only to 2 tables.
     *
     * @param	string		$name Name of the select-field
     * @param	string		$cur Table name, which is currently selected
     *
     * @return	string		HTML select-field
     * @see QueryGenerator::mkTableSelect()
     */
    public function mkTableSelect($name, $cur)
    {
        $out = [];
        $out[] = '<select class="form-select t3js-submit-change" name="' . $name . '">';
        $out[] = '<option value=""></option>';
        foreach ($GLOBALS['TCA'] as $tN => $value) {
            if ($this->getBackendUserAuthentication()->check('tables_select', $tN) && in_array($tN, $this->allowedTables)) {
                $out[] = '<option value="' . htmlspecialchars($tN) . '"' . ($tN == $cur ? ' selected' : '') . '>' . htmlspecialchars($this->getLanguageService()->sl($GLOBALS['TCA'][$tN]['ctrl']['title'])) . '</option>';
            }
        }
        $out[] = '</select>';
        return implode(LF, $out);
    }

}
