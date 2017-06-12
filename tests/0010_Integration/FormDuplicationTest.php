<?php
class FormDuplicationTest extends SuperAdminTestCase
{

   protected $formData;
   protected $sectionData;
   protected $targetData;

   public function setUp() {
      parent::setUp();

      $this->formData = array(
            'entities_id'           => $_SESSION['glpiactive_entity'],
            'name'                  => 'a form',
            'description'           => 'form description',
            'content'               => 'a content',
            'is_active'             => 1,
            'validation_required'   => 0
      );

      $this->sectionData = array(
            array(
                  'name'                  => 'a section',
                  'questions'             => array (
                        array(
                              'name'                  => 'text question',
                              'fieldtype'             => 'text'
                        ),
                        array(
                              'name'                  => 'other text question',
                              'fieldtype'             => 'text'
                        ),
                  ),
            ),
            array(
                  'name'                  => 'an other section',
                  'questions'             => array (
                        array(
                              'name'                  => 'text question',
                              'fieldtype'             => 'text'
                        ),
                        array(
                              'name'                  => 'other text question',
                              'fieldtype'             => 'text',
                              'show_rule'             => 'hidden',
                              'show_field'            => 'text question',
                              'show_condition'        => '==',
                              'show_value'            => 'azerty',
                        ),
                  ),
            ),
      );

      $this->targetData = array(
            array(
                  'name'                  => 'target ticket 1',
                  'itemtype'              => 'PluginFormcreatorTargetTicket',
            ),
            array(
                  'name'                  => 'target ticket 2',
                  'itemtype'              => 'PluginFormcreatorTargetTicket',
            )
      );
   }

   public function testInitCreateForm() {
      $form = new PluginFormcreatorForm();
      $formId = $form->add($this->formData);
      $this->assertFalse($form->isNewItem());

      foreach ($this->sectionData as $sectionData) {
         // Keep questions data set apart from sections data
         $questionsData = $sectionData['questions'];
         unset($sectionData['questions']);

         // Create section
         $sectionData['plugin_formcreator_forms_id'] = $form->getID();
         $section = new PluginFormcreatorSection();
         $section->add($sectionData);
         $this->assertFalse($section->isNewItem());
         $sectionId = $section->getID();
         foreach ($questionsData as $questionData) {
            // Create question
            $questionData ['plugin_formcreator_sections_id'] = $section->getID();
            $question = new PluginFormcreatorQuestion();
            $question->add($questionData);
            $this->assertFalse($question->isNewItem(), $_SESSION['MESSAGE_AFTER_REDIRECT']);

            $questionData['id'] = $question->getID();
            if (isset($questionData['show_rule']) && $questionData['show_rule'] != 'always') {
               $showFieldName = $questionData['show_field'];
               $showfield = new PluginFormcreatorQuestion();
               $showfield->getFromDBByQuery("WHERE `plugin_formcreator_sections_id` = '$sectionId' AND `name` = '$showFieldName'");
               $questionData['show_field'] = $showfield->getID();
               $question->updateConditions($questionData);
            }
         }
         foreach ($this->targetData as $targetData) {
            $target = new PluginFormcreatorTarget();
            $targetData['plugin_formcreator_forms_id'] = $formId;
            $target->add($targetData);
            $this->assertFalse($target->isNewItem());
         }
      }

      return $form;
   }

   /**
    * @depends testInitCreateForm
    * @param PluginFormcreatorForm $form
    */
   public function testDuplicateForm(PluginFormcreatorForm $form) {
      $sourceFormId = $form->getID();
      $this->assertTrue($form->duplicate());

      // Check the ID of the form changed
      $newFormId = $form->getID();
      $this->assertNotEquals($sourceFormId, $newFormId);

      // Check sections were copied
      $section = new PluginFormcreatorSection();
      $sourceRows = $section->find("`plugin_formcreator_forms_id` = '$sourceFormId'");
      $newRows = $section->find("`plugin_formcreator_forms_id` = '$newFormId'");
      $this->assertEquals(count($sourceRows), count ($newRows));

      // Check questions were copied
      $table_section = PluginFormcreatorSection::getTable();
      $question = new PluginFormcreatorQuestion();
      $sourceRows = $question->find("`plugin_formcreator_sections_id` IN (
            SELECT `id` FROM `$table_section` WHERE `$table_section`.`plugin_formcreator_forms_id` = '$sourceFormId'
      )");
      $newRows = $question->find("`plugin_formcreator_sections_id` IN (
            SELECT `id` FROM `$table_section` WHERE `$table_section`.`plugin_formcreator_forms_id` = '$newFormId'
      )");
      $this->assertEquals(count($sourceRows), count($newRows));

      // check target were created
      $target = new PluginFormcreatorTarget();
      $sourceRows = $target->find("`plugin_formcreator_forms_id` = '$sourceFormId'");
      $newRows = $target->find("`plugin_formcreator_forms_id` = '$sourceFormId'");
      $this->assertEquals(count($sourceRows), count($newRows));

      // check target tickets were created
      foreach ($newRows as $targetId => $newTarget) {
         if ($newTarget['itemtype'] == 'PluginFormcreatorTargetTicket') {
            $targetTicket = new PluginFormcreatorTArgetTicket();
            $targetTicket->getFromDB($newTarget['items_id']);
            $this->assertFalse($targetTicket->isNewItem());
         }
      }

   }
}