import React from 'react';
import {Minus, Plus} from "lucide-react"
import type {PosItem} from './types';
import './POSPage.scss';

interface POSItemProps {
    item: PosItem;
    onAdd: (item: PosItem) => void;
    onRemove: (id: string) => void;
    quantity: number;
}

export function POSItem({item, onAdd, onRemove, quantity}: POSItemProps) {
    return (
        <div key={item.producto_id} className="bg-white rounded-xl p-4">
            <div className="flex items-center gap-3 mb-2">
                <img
                    src={item.imagen?.path || `https://ui-avatars.com/api/?name=${item.descripcion}`}
                    alt={item.descripcion}
                    className="w-16 h-16 object-cover rounded-md"
                />
                <div className="text-sm font-medium">
                    {item.descripcion}
                </div>
            </div>
            <div className="flex items-center justify-between">
                <span className="text-2xl font-bold">
                    <span className="text-gray-500">S/</span>{item.precio.toFixed(2)}
                </span>
                <div className="flex items-center rounded-full bg-gray-100 border border-gray-200">
                    <button
                        onClick={() => onRemove(item.producto_id)}
                        className="w-8 h-8 flex items-center justify-center rounded-full bg-white"
                    >
                        <Minus className="w-4 h-4"/>
                    </button>
                    <span className="w-8 text-center text-gray-500">{quantity || 0}</span>
                    <button
                        onClick={() => onAdd(item)}
                        className={`w-8 h-8 flex items-center justify-center rounded-full ${
                            quantity > 0 ? 'bg-blue-500 text-white' : 'bg-white text-blue-500'
                        }`}
                    >
                        <Plus className="w-4 h-4"/>
                    </button>
                </div>
            </div>
        </div>
    );
}

