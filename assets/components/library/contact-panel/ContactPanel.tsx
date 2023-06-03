import React, {useCallback, useEffect, useState} from 'react';

import './ContactPanel.scss';

import {Contact} from '../../../types/crm-contact';
import {withLoadPanel} from '../../../utils/withLoadPanel';

import {ContactPanelDetails} from './ContactPanelDetails';
import {productService} from "../../../services/product.service";
import {Activities} from "../../../types/card-activities";
import {Opportunities} from "../../../types/card-opportunities";

const ContactPanelWithLoadPanel = withLoadPanel(ContactPanelDetails);

export const ContactPanel = ({contactId, isOpened, changePanelOpened, changePanelPinned}: {
    contactId: string | null,
    isOpened: boolean,
    changePanelOpened: (value: boolean) => void,
    changePanelPinned: () => void
}) => {

    const [data, setData] = useState<Contact>();

    const loadData = useCallback(() => {
        if (!contactId) return;

        productService.getOne(contactId).then((data) => {
            let contact: Contact = {
                id: 1,
                name: data.descripcion,
                status: data.descripcion,
                city: data.descripcion,
                state: {} as any,
                zipCode: 0,
                activities: {} as Activities,
                opportunities: [] as Opportunities,
                tasks: [],
                address: data.descripcion,
                firstName: data.descripcion,
                lastName: data.descripcion,
                position: data.descripcion,
                manager: data.descripcion,
                company: data.descripcion,
                phone: data.descripcion,
                email: data.descripcion,
                image: data.descripcion,
            };
            setData(contact);
        });
    }, [contactId]);

    const onDataChanged = useCallback(data => {
        setData(data);
    }, []);

    useEffect(() => {
        loadData();
    }, [loadData]);

    return (
        <ContactPanelWithLoadPanel
            contact={data}
            hasData={!!data}
            isOpened={isOpened}
            onDataChanged={onDataChanged}
            changePanelOpened={changePanelOpened}
            changePanelPinned={changePanelPinned}
            panelProps={{
                position: {of: '.contact-panel'},
                container: '.contact-panel'
            }}
        />
    );
};

