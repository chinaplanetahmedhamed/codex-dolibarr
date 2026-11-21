<?php

/** Disabled-by-default boundary for future privacy-approved contract extraction. */
class QcContractProposalAdapter
{
    public static function configured(){return false;}
    public function extract($privateDocumentPath){throw new RuntimeException('Contract extraction provider is disabled pending separate privacy and provider approval.');}
}
