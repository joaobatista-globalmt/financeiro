<?php
/**
 * Endpoint: ibs_cbs_regras_listar.php
 * Lista as regras de tributacao IBS/CBS por grupo NBS.
 */
require __DIR__ . '/bootstrap.php';
(new CnaeServicoController)->regrasIbsCbs();
