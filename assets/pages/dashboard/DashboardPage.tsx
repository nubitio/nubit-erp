import {useEffect} from "react";
import Dashboard from "../Dashboard";

export const DashboardPage = () => {
    const container = "wrapper"
    const dashboard = new Dashboard(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            dashboard.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
            <div className="container-fluid">
                <div className="row" style={{marginTop: '15px'}}>
                    <div className="col-md-12 mb-3" style={{minHeight: '425px'}}>
                        <div id="rotation-panel">
                            <div className="card">
                                <div className="card-header" style={{padding: '8px 10px'}}>
                                    <div className="row">
                                        <div className="col-md-6">
                                            <h6>Rotación de productos</h6>
                                        </div>
                                        <div className="col-md-6 text-end">
                                            <input id="lapse-selector"/>
                                        </div>
                                    </div>
                                </div>
                                <div className="card-body" style={{padding: '8px'}}>
                                    <div className="row">
                                        <div className="col-md-4">
                                            <div className="card-panel" id="most-sold"
                                                 style={{minHeight: '350px'}}></div>
                                        </div>
                                        <div className="col-md-4">
                                            <div className="card-panel" id="less-sold"
                                                 style={{minHeight: '350px'}}></div>
                                        </div>
                                        <div className="col-md-4">
                                            <table id="without-rotation"></table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="col-md-6">
                        <div className="card-panel chart-box" id="venta_semana"
                             style={{minWidth: '320px', height: '340px'}}></div>
                    </div>
                    <div className="col-md-6">
                        <div className="card-panel chart-box" id="venta_mes"
                             style={{minWidth: '320px', height: '340px'}}></div>
                    </div>
                    <div className="col-md-6">
                        <div className="card-panel chart-box" id="venta_dia"
                             style={{minWidth: '320px', height: '400px'}}></div>
                    </div>
                    <div className="col-md-6">
                        <div className="card-panel chart-box" id="monthly_invoicing"
                             style={{minWidth: '320px', height: '400px'}}></div>
                    </div>
                </div>
            </div>
        </div>
    );
};