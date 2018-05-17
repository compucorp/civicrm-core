'use strict';

fdescribe('crmCaseType', function() {
  var $controller;
  var $compile;
  var $httpBackend;
  var $q;
  var $rootScope;
  var $timeout;
  var apiCalls;
  var ctrl;
  var compile;
  var scope;

  beforeEach(function() {
    CRM.resourceUrls = {
      'civicrm': ''
    };
    // CRM_Case_XMLProcessor::REL_TYPE_CNAME
    CRM.crmCaseType = {
      'REL_TYPE_CNAME': 'label_b_a'
    };
    module('crmCaseType');
    module('crmJsonComparator');
    inject(function(crmJsonComparator) {
      crmJsonComparator.register(jasmine);
    });
  });

  beforeEach(inject(function(_$controller_, _$compile_, _$httpBackend_, _$q_, _$rootScope_, _$timeout_) {
    $controller = _$controller_;
    $compile = _$compile_;
    $httpBackend = _$httpBackend_;
    $q = _$q_;
    $rootScope = _$rootScope_;
    $timeout = _$timeout_;
  }));

  describe('CaseTypeCtrl', function() {
    beforeEach(inject(function() {
      apiCalls = {
        actStatuses: {
          values: [
            {
              "id": "272",
              "option_group_id": "25",
              "label": "Scheduled",
              "value": "1",
              "name": "Scheduled",
              "filter": "0",
              "is_default": "1",
              "weight": "1",
              "is_optgroup": "0",
              "is_reserved": "1",
              "is_active": "1"
            },
            {
              "id": "273",
              "option_group_id": "25",
              "label": "Completed",
              "value": "2",
              "name": "Completed",
              "filter": "0",
              "weight": "2",
              "is_optgroup": "0",
              "is_reserved": "1",
              "is_active": "1"
            }
          ]
        },
        caseStatuses: {
          values: [
            {
              "id": "290",
              "option_group_id": "28",
              "label": "Ongoing",
              "value": "1",
              "name": "Open",
              "grouping": "Opened",
              "filter": "0",
              "is_default": "1",
              "weight": "1",
              "is_optgroup": "0",
              "is_reserved": "1",
              "is_active": "1"
            },
            {
              "id": "291",
              "option_group_id": "28",
              "label": "Resolved",
              "value": "2",
              "name": "Closed",
              "grouping": "Closed",
              "filter": "0",
              "weight": "2",
              "is_optgroup": "0",
              "is_reserved": "1",
              "is_active": "1"
            }
          ]
        },
        actTypes: {
          values: [
            {
              "id": "784",
              "option_group_id": "2",
              "label": "ADC referral",
              "value": "62",
              "name": "ADC referral",
              "filter": "0",
              "is_default": "0",
              "weight": "64",
              "is_optgroup": "0",
              "is_reserved": "0",
              "is_active": "1",
              "component_id": "7"
            },
            {
              "id": "32",
              "option_group_id": "2",
              "label": "Add Client To Case",
              "value": "27",
              "name": "Add Client To Case",
              "filter": "0",
              "is_default": "0",
              "weight": "26",
              "description": "",
              "is_optgroup": "0",
              "is_reserved": "1",
              "is_active": "1",
              "component_id": "7"
            },
            {
              "id": "18",
              "option_group_id": "2",
              "label": "Open Case",
              "value": "13",
              "name": "Open Case",
              "filter": "0",
              "is_default": "0",
              "weight": "13",
              "is_optgroup": "0",
              "is_reserved": "1",
              "is_active": "1",
              "component_id": "7",
              "icon": "fa-folder-open-o"
            },
            {
              "id": "857",
              "option_group_id": "2",
              "label": "Medical evaluation",
              "value": "55",
              "name": "Medical evaluation",
              "filter": "0",
              "is_default": "0",
              "weight": "56",
              "is_optgroup": "0",
              "is_reserved": "0",
              "is_active": "1",
              "component_id": "7"
            },
          ]
        },
        relTypes: {
          values: [
            {
              "id": "14",
              "name_a_b": "Benefits Specialist is",
              "label_a_b": "Benefits Specialist is",
              "name_b_a": "Benefits Specialist",
              "label_b_a": "Benefits Specialist",
              "description": "Benefits Specialist",
              "contact_type_a": "Individual",
              "contact_type_b": "Individual",
              "is_reserved": "0",
              "is_active": "1"
            },
            {
              "id": "9",
              "name_a_b": "Case Coordinator is",
              "label_a_b": "Case Coordinator is",
              "name_b_a": "Case Coordinator",
              "label_b_a": "Case Coordinator",
              "description": "Case Coordinator",
              "contact_type_a": "Individual",
              "contact_type_b": "Individual",
              "is_reserved": "0",
              "is_active": "1"
            }
          ]
        },
        caseType: {
          "id": "1",
          "name": "housing_support",
          "title": "Housing Support",
          "description": "Help homeless individuals obtain temporary and long-term housing",
          "is_active": "1",
          "is_reserved": "0",
          "weight": "1",
          "is_forkable": "1",
          "is_forked": "",
          "definition": {
            "activityTypes": [
              {"name": "Open Case", "max_instances": "1"}
            ],
            "activitySets": [
              {
                "name": "standard_timeline",
                "label": "Standard Timeline",
                "timeline": "1",
                "activityTypes": [
                  {
                    "name": "Open Case",
                    "status": "Completed"
                  },
                  {
                    "name": "Medical evaluation",
                    "reference_activity": "Open Case",
                    "reference_offset": "1",
                    "reference_select": "newest"
                  }
                ]
              }
            ],
            "caseRoles": [
              {
                "name": "Homeless Services Coordinator",
                "creator": "1",
                "manager": "1"
              }
            ]
          }
        }
      };
      ctrl = $controller('CaseTypeCtrl', {$scope: scope, apiCalls: apiCalls});
    }));

    it('should load activity statuses', function() {
      expect(scope.activityStatuses).toEqualData(apiCalls.actStatuses.values);
    });

    it('should load activity types', function() {
      expect(scope.activityTypes['ADC referral']).toEqualData(apiCalls.actTypes.values[0]);
    });

    it('addActivitySet should add an activitySet to the case type', function() {
      scope.addActivitySet('timeline');
      var activitySets = scope.caseType.definition.activitySets;
      var newSet = activitySets[activitySets.length - 1];
      expect(newSet.name).toBe('timeline_1');
      expect(newSet.timeline).toBe('1');
      expect(newSet.label).toBe('Timeline');
    });

    it('addActivitySet handles second timeline correctly', function() {
      scope.addActivitySet('timeline');
      scope.addActivitySet('timeline');
      var activitySets = scope.caseType.definition.activitySets;
      var newSet = activitySets[activitySets.length - 1];
      expect(newSet.name).toBe('timeline_2');
      expect(newSet.timeline).toBe('1');
      expect(newSet.label).toBe('Timeline #2');
    });

  });

  describe('crmEditableTabTitle', function () {
    var element, titleLabel, penIcon, saveButton, cancelButton;

    beforeEach(function() {
      scope = $rootScope.$new();
      element = '<div crm-editable-tab-title title="Click to edit">' +
        '<span>{{ activitySet.label }}</span>' +
        '</div>';

      scope.activitySet = { label: 'Title'};
      element = $compile(element)(scope);

      titleLabel = $(element).find('span');
      penIcon = $(element).find('i.fa-pencil');
      saveButton = $(element).find('button[type=button]');
      cancelButton = $(element).find('button[type=cancel]');

      scope.$digest();
    });

    describe('when initialized', function () {
      it('hides the save and cancel button', function () {
        expect(saveButton.parent().css('display') === 'none').toBe(true);
        expect(cancelButton.parent().css('display') === 'none').toBe(true);
      });
    });

    describe('when clicked on title label', function () {
      beforeEach(function () {
        titleLabel.click();
      });

      it('hides the pen icon', function () {
        expect(penIcon.css('display') === 'none').toBe(true);
      });

      it('shows the save button', function () {
        expect(saveButton.parent().css('display') !== 'none').toBe(true);
      });

      it('makes the title editable', function () {
        expect(titleLabel.attr('contenteditable')).toBe('true');
      });
    });

    describe('when clicked outside of the editable area', function () {
      beforeEach(function () {
        titleLabel.click();
        titleLabel.text('Updated Title');
        titleLabel.blur();
        $timeout.flush();
        scope.$digest();
      });

      it('shows the pen icon', function () {
        expect(penIcon.css('display') !== 'none').toBe(true);
      });

      it('hides the save and cancel button', function () {
        expect(saveButton.parent().css('display') === 'none').toBe(true);
        expect(cancelButton.parent().css('display') === 'none').toBe(true);
      });

      it('makes the title non editable', function () {
        expect(titleLabel.attr('contenteditable')).not.toBe('true');
      });

      it('does not update the title in angular context', function () {
        expect(scope.activitySet.label).toBe('Title');
      });
    });

    describe('when ESCAPE key is pressed while typing', function () {
      beforeEach(function () {
        var eventObj = $.Event('keydown');
        eventObj.key = 'Escape';

        titleLabel.click();
        titleLabel.text('Updated Title');
        titleLabel.trigger(eventObj);
        scope.$digest();
      });

      it('shows the pen icon', function () {
        expect(penIcon.css('display') !== 'none').toBe(true);
      });

      it('hides the save and cancel button', function () {
        expect(saveButton.parent().css('display') === 'none').toBe(true);
        expect(cancelButton.parent().css('display') === 'none').toBe(true);
      });

      it('makes the title non editable', function () {
        expect(titleLabel.attr('contenteditable')).not.toBe('true');
      });

      it('does not update the title', function () {
        expect(scope.activitySet.label).toBe('Title');
      });
    });

    describe('when ENTER key is pressed while typing', function () {
      beforeEach(function () {
        var eventObj = $.Event('keydown');
        eventObj.key = 'Enter';

        titleLabel.click();
        titleLabel.text('Updated Title');
        titleLabel.trigger(eventObj);
        scope.$digest();
      });

      it('shows the pen icon', function () {
        expect(penIcon.css('display') !== 'none').toBe(true);
      });

      it('hides the save and cancel button', function () {
        expect(saveButton.parent().css('display') === 'none').toBe(true);
        expect(cancelButton.parent().css('display') === 'none').toBe(true);
      });

      it('makes the title non editable', function () {
        expect(titleLabel.attr('contenteditable')).not.toBe('true');
      });

      it('updates the title in angular context', function () {
        expect(scope.activitySet.label).toBe('Updated Title');
      });
    });

    describe('when SAVE button is clicked', function () {
      beforeEach(function () {
        titleLabel.click();
        titleLabel.text('Updated Title');
        saveButton.click();
        scope.$digest();
      });

      it('shows the pen icon', function () {
        expect(penIcon.css('display') !== 'none').toBe(true);
      });

      it('hides the save and cancel button', function () {
        expect(saveButton.parent().css('display') === 'none').toBe(true);
        expect(cancelButton.parent().css('display') === 'none').toBe(true);
      });

      it('makes the title non editable', function () {
        expect(titleLabel.attr('contenteditable')).not.toBe('true');
      });

      it('updates the title in angular context', function () {
        expect(scope.activitySet.label).toBe('Updated Title');
      });
    });

    describe('when CANCEL button is clicked', function () {
      beforeEach(function () {
        titleLabel.click();
        titleLabel.text('Updated Title');
        cancelButton.click();
        scope.$digest();
      });

      it('shows the pen icon', function () {
        expect(penIcon.css('display') !== 'none').toBe(true);
      });

      it('hides the save and cancel button', function () {
        expect(saveButton.parent().css('display') === 'none').toBe(true);
        expect(cancelButton.parent().css('display') === 'none').toBe(true);
      });

      it('makes the title non editable', function () {
        expect(titleLabel.attr('contenteditable')).not.toBe('true');
      });

      it('does not update the title in angular context', function () {
        expect(scope.activitySet.label).toBe('Title');
      });
    });
  });
});
