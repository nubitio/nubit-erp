import "./MasterDetailPage.scss";
import React from "react";
import {Item, Toolbar} from "devextreme-react/toolbar";
import Button from "devextreme-react/button";

type MasterDetailViewProps = {
    title: string;
    showBackButton?: boolean;
    leftComponent: any;
    rightComponent: any;
}

export const MasterDetailPage = (props: MasterDetailViewProps) => {
    const container = "wrapper";

    return (
        <div id={container} className='panels-wrapper'>
            <Toolbar className='toolbar-details'>
                {(props.showBackButton ?? true) &&
                    <Item location='before'>
                        <Button icon='arrowleft' onClick={() => {
                            window.history.back();
                        }}/>
                    </Item>
                }
                <Item location='before' text={props.title}/>
            </Toolbar>
            <div className='panels'>
                <div className='left'>
                    {props.leftComponent}
                </div>
                <div className='right'>
                    {props.rightComponent}
                </div>
            </div>
        </div>
    );
};
