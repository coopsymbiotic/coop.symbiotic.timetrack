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
   * Convert the kpunch.begin to a mysql datetime field.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');

    // Rename the old column, add a new 'begin' column,
    // then migrate the data over.
    CRM_Core_DAO::executeQuery('ALTER TABLE kpunch CHANGE begin begin_old int(11) NOT NULL');
    CRM_Core_DAO::executeQuery('ALTER TABLE kpunch ADD begin datetime NOT NULL AFTER begin_old');
    CRM_Core_DAO::executeQuery('UPDATE kpunch SET begin = FROM_UNIXTIME(begin_old)');
    CRM_Core_DAO::executeQuery('ALTER TABLE kpunch DROP begin_old');

    return TRUE;
  }

  /**
   * Rename the ktask table to civicrm_timetracktask
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');

    // Remove some deprecated fields
    CRM_Core_DAO::executeQuery('ALTER TABLE ktask DROP COLUMN activity_id');
    CRM_Core_DAO::executeQuery('ALTER TABLE ktask DROP INDEX parent');
    CRM_Core_DAO::executeQuery('ALTER TABLE ktask DROP COLUMN parent');

    // Remove the Foreign Keys from other tables that depend on ktask
    CRM_Core_DAO::executeQuery('ALTER TABLE kpunch DROP FOREIGN KEY FK_ktask_id');
    CRM_Core_DAO::executeQuery('ALTER TABLE korder_line DROP FOREIGN KEY FK_korder_line_ktask_id');

    // Rename ktask and add FKs back
    CRM_Core_DAO::executeQuery('RENAME TABLE ktask TO civicrm_timetracktask');
    CRM_Core_DAO::executeQuery('ALTER TABLE kpunch ADD CONSTRAINT FK_ktask_id FOREIGN KEY (ktask_id) REFERENCES civicrm_timetracktask(id)');
    CRM_Core_DAO::executeQuery('ALTER TABLE korder_line ADD CONSTRAINT FK_korder_line_ktask_id FOREIGN KEY (ktask_id) REFERENCES civicrm_timetracktask(id)');

    return TRUE;
  }

}
