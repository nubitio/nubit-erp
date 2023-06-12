import React, {useState} from 'react';

import Form, {ColCountByScreen, GroupItem, Item as FormItem} from 'devextreme-react/form';
import {Contact} from '../../types/crm-contact';
import {newContact} from '../../shared/constants';
import {FormPhotoUploader, FormTextbox} from '../../components';
import {EmailRule} from 'devextreme-react/validator';
import {getSizeQualifier} from '../../utils/media-query';

export const ProductForm = () => {
    const [productData, setProductData] = useState<Contact>(newContact);

    const updateField = (field: string) => (value) => {
        setProductData((prevState) => ({...prevState, ...{[field]: value}}));
    };

    return (
        <Form
            className='plain-styled-form'
            screenByWidth={getSizeQualifier}
        >
            <GroupItem>
                <ColCountByScreen xs={1} sm={1} md={1} lg={1}/>
                <FormItem>
                    <FormPhotoUploader/>
                </FormItem>
            </GroupItem>

            <GroupItem>
                <ColCountByScreen xs={1} sm={2} md={2} lg={2}/>
                <FormItem>
                    <FormTextbox
                        label='First Name'
                        value={productData.firstName}
                        isEditing={false}
                        onValueChange={updateField('firstName')}
                    />
                </FormItem>
                <FormItem>
                    <FormTextbox
                        label='Last Name'
                        value={productData.lastName}
                        isEditing={false}
                        onValueChange={updateField('lastName')}
                    />
                </FormItem>
                <FormItem>
                    <FormTextbox
                        label='Company'
                        value={productData.company}
                        isEditing={false}
                        onValueChange={updateField('company')}
                    />
                </FormItem>
                <FormItem>
                    <FormTextbox
                        label='Position'
                        value={productData.position}
                        isEditing={false}
                        onValueChange={updateField('position')}
                    />
                </FormItem>
            </GroupItem>

            <GroupItem cssClass='contact-fields-group'>
                <ColCountByScreen xs={1} sm={2} md={2} lg={2}/>
                <FormItem>
                    <FormTextbox
                        value={productData.manager}
                        label='Assigned to'
                        isEditing={false}
                        onValueChange={updateField('manager')}
                    />
                </FormItem>
                <FormItem>
                    <FormTextbox
                        value={productData.phone}
                        isEditing={false}
                        onValueChange={updateField('phone')}
                        icon='tel'
                        mask='+1(000)000-0000'
                    />
                </FormItem>
                <FormItem>
                    <FormTextbox
                        value={productData.email}
                        onValueChange={updateField('email')}
                        isEditing={false}
                        icon='email'
                    >
                        <EmailRule/>
                    </FormTextbox>
                </FormItem>
                <FormItem>
                    <FormTextbox
                        value={productData.address}
                        isEditing={false}
                        onValueChange={updateField('address')}
                        icon='home'
                    />
                </FormItem>
            </GroupItem>
        </Form>
    );
};
