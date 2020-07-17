<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Timetrack_Upgrader extends CRM_Timetrack_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Run an external SQL script when the module is installed.
   */
  public function install() {
    $this->executeSqlFile('sql/install.sql');
  }

  /**
   * Run an external SQL script when the module is uninstalled.
   */
  public function uninstall() {
    // @todo
    // $this->executeSqlFile('sql/myuninstall.sql');
  }

  /**
   * Convert the kpunch.begin to a mysql datetime field.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 1001');

    // Rename the old column, add a new 'begin' column,
    // then migrate the data over.
    CRM_Core_DAO::executeQuery('ALTER TABLE kpunch CHANGE begin begin_old int(11) NOT NULL');
    CRM_Core_DAO::executeQuery('ALTER TABLE kpunch ADD begin datetime NOT NULL AFTER begin_old');
    CRM_Core_DAO::executeQuery('UPDATE kpunch SET begin = FROM_UNIXTIME(begin_old)');
    CRM_Core_DAO::executeQuery('ALTER TABLE kpunch DROP begin_old');

    return TRUE;
  }

}
