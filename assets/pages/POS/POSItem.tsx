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
            <img
                src={item.imagen?.path || "https://fakeimg.pl/600x400?text=" + item.descripcion}
                alt={item.descripcion}
                className="w-full h-32 object-cover rounded-lg mb-4"
            />
            <h4 className="font-bold text-lg mb-2 marquee-container">
                <span className="marquee">{item.descripcion}</span>
            </h4>
            <p className="text-gray-600 text-sm mb-4 marquee-container">
                <span className="marquee">{item.descripcion}</span>
            </p>
            <div className="flex items-center justify-between">
                <span className="text-2xl font-bold">${item.precio.toFixed(2)}</span>
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => onRemove(item.producto_id)}
                        className="w-8 h-8 flex items-center justify-center rounded-full border"
                    >
                        <Minus className="w-4 h-4"/>
                    </button>
                    <span className="w-8 text-center">{quantity || 0}</span>
                    <button
                        onClick={() => onAdd(item)}
                        className={`w-8 h-8 flex items-center justify-center rounded-full border ${
                            quantity > 0 ? 'bg-blue-500 text-white' : 'text-blue-500'
                        }`}
                    >
                        <Plus className="w-4 h-4"/>
                    </button>
                </div>
            </div>
        </div>
    );
}

