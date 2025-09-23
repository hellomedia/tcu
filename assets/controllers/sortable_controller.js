// bin/console importmap:require @stimulus-components/sortable
import SortableController from '@stimulus-components/sortable'

import '../styles/component/sortable.css';

// This controller has access to targets defined in the parent class.

export default class extends SortableController {

    static values = {
        url: String,
        param: String,
    }

    onUpdate(event) {
        
        super.onUpdate(event)

        // Grab ordered IDs
        const ids = this.sortable.toArray();

        // Send AJAX request (can use Fetch API or Axios)
        fetch(this.urlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest', // Symfony expects this
            },
            body: JSON.stringify({
                [this.paramValue]: ids // { image_ids: [3,1,2] }
            }),
        }).then(response => {
            if (response.ok) {
                console.log('Order updated!');
            } else {
                console.error('Error updating order');
            }
        })
    }
}