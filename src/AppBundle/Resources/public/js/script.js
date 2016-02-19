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

});