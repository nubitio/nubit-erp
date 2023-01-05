import React, {Component} from "react";

export class CrudHeader extends Component<any, any> {

    constructor(props: any) {
        super(props);
    }

    renderAddButton() {
        if (this.props.showAddButton) {
            return (
                <button className="btn btn-primary">Add</button>
            );
        }
        return null;
    }

    render() {
        return (
            <div className="container-fluid">
                <div className="p-3">
                    <div className="float-start">
                        <div className="h5 mb-0">{this.props.pageTitle}</div>
                    </div>

                    <div className="float-end">
                        {this.renderAddButton()}
                    </div>
                </div>
                <div className="clearfix"></div>
            </div>
        );
    }
}