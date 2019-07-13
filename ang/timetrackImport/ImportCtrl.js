(function(angular, $, _) {

  angular.module('timetrackImport').config(function($routeProvider) {
      $routeProvider.when('/import', {
        controller: 'TimetrackImportImportCtrl',
        templateUrl: '~/timetrackImport/ImportCtrl.html',
        resolve: {}
      });
    }
  );

  angular.module('timetrackImport').controller('TimetrackImportImportCtrl', function($scope, crmApi, crmStatus, crmUiHelp, crmUiAlert) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('timetrack');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/timetrackImport/ImportCtrl'}); // See: templates/CRM/timetrackImport/ImportCtrl.hlp

    $scope.timetrack_help_url = CRM.timetrackImport.timetrack_help_url;

    function newModel() {
      return {
        plaintext: '',
        punches: [],
        errors: []
      };
    }

    $scope.importModel = newModel();

    function debounce(time, func) {
      return _.debounce(function(){
        var a = arguments;
        $scope.$evalAsync(function(){
          func.apply(a);
        });
      }, time);
    }

    $scope.updatePreview = debounce(100, function(){
      crmApi('Timetrackpunchlist', 'preview', {
        text: $scope.importModel.plaintext
      }).then(function(apiResult){
        console.log('rx ' + new Date(), apiResult);
        $scope.importModel.punches = _.filter(apiResult.values, function(punch){ return !punch.error;});
        $scope.importModel.errors = _.filter(apiResult.values, function(punch){ return !!punch.error;});
      });
    });

    $scope.$watch('importModel.plaintext', function(plainText){
      $scope.updatePreview();
    });

    $scope.submit = function submit() {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Importing...'), success: ts('Imported')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Timetrackpunchlist', 'import', {
          text: $scope.importModel.plaintext
        })
          .then(function(apiResult){
            $scope.lastImport = apiResult.values;

            crmUiAlert({title: ts('Imported'), text: apiResult.values.length + ' record(s)', type: 'success'});
            $scope.importModel = newModel();
          })
      );
    };
  });

})(angular, CRM.$, CRM._);
