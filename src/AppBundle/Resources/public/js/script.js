$(function(){

    $(document).on('click', '.processModalButton', function(e){
        e.preventDefault();

        var target = $(this).attr('data-target');
        var modal = $(this).parents('.modal');
        var modalStatus = modal.find('.modal-body > .status');
        var formData = modal.find('form').serialize();

        modal.find('.btn').addClass('disabled');

        $.post(target, formData, function(response){
            modal.find('.btn').removeClass('disabled');

            if (!('status' in response))
            {
                alert('Invalid response');
                console.log(response);
                return;
            }

            modalStatus.html('<div class="alert alert-' + (response.status ? 'success' : 'danger') + '" role="alert">' + (response.message ? response.message : 'Success!') + '</div>');
            if (response.status)
                window.location.reload();
        });
    });

    var replicationsCount = 1;
    $(document).on('click', '.addReplicationRow', function(e){
        e.preventDefault();

        var listGroup = $(this).siblings('.list-group');
        var dummy = listGroup.children().first().clone();

        dummy
            .find('.removeReplicationRow')
            .removeClass('hide');

        dummy
            .attr('data-count', replicationsCount);

        dummy
            .find('.replicationDatacenter')
            .attr('name', 'replication[' + replicationsCount + '][datacenter]')
            .val('');

        dummy
            .find('.replicationFactor')
            .attr('name', 'replication[' + replicationsCount + '][factor]')
            .val('');

        listGroup.append(dummy);

        replicationsCount++;
    });

    $(document).on('click', '.removeReplicationRow', function(e){
        e.preventDefault();

        var groupItem = $(this).parents('li.list-group-item');

        if (groupItem.attr('data-count') > 0)
            groupItem.remove();
    });

    $(document).on('change', '#addKeyspaceModal #class', function(){
        $('.VariableKeyspaceInput').addClass('hide');
        $('.' + $(this).val() + 'Input').removeClass('hide');
    });

    $(document).on('click', '.removeModalProcess', function(e){
        e.preventDefault();

        var removeWarningDialog = $('#removeWarningDialog');

        removeWarningDialog
            .find('a.proceed')
            .attr('href', $(this).attr('href'));

        removeWarningDialog
            .modal('show');
    });

    var columnFamilyFieldId = 1;
    $(document).on('click', '.addColumnFamilyField', function(e){
        e.preventDefault();

        var dummy = $('#columnFamilyFieldDummyRow').clone();

        dummy
            .removeAttr('id')
            .attr('data-id', columnFamilyFieldId);
        dummy.find('input[name="prefix"]').attr('name', 'prefix[' + columnFamilyFieldId + ']');

        $('#columnFamilyFieldTable tbody').append(dummy);
        columnFamilyFieldId++;
    });

    $(document).on('click', '.removeColumnFamilyField', function(e){
        e.preventDefault();

        $(this).parents('tr').remove();
    });

    $(document).on('change', '#columnFamilyFieldTable select[name="type"]', function(e){
        e.preventDefault();

        var type = $(this).val();
        var addFields = 0;

        if (type == 'set' || type == 'list' || type == 'tuple')
            addFields = 1;
        else if (type == 'map')
            addFields = 2;

        var dummyField = $(this).clone();

        // clean up the old ones
        var next = $(this).next();
        if (next.length > 0 && next.hasClass('container-fluid'))
            next.remove();

        // early exit
        if (addFields == 0)
            return;

        // create container for some spacing
        var container = $('<div class="container-fluid" />');

        // fill field fields
        for (var i=0; i<addFields; i++)
            container.append(dummyField.clone());

        // add "add"-button
        if (type == 'tuple')
            container.append('<a href="#" class="btn btn-xs btn-success addDynamicFieldType"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span></a>');

        // display everything
        $(this).after(container);
    });

    $(document).on('click', '.addDynamicFieldType', function(e){
        e.preventDefault();

        //$(this).before($(this).prevAll('select').clone());
    });

});