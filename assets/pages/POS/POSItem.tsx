import React from 'react';
import {Button} from 'devextreme-react/button';
import type {MenuItem as MenuItemType} from './types';

interface POSItemProps {
    item: MenuItemType;
    onAdd: (item: MenuItemType) => void;
    onRemove: (id: number) => void;
    quantity: number;
}

export function POSItem({item, onAdd, onRemove, quantity}: POSItemProps) {
    return (
        <div className="bg-white rounded-2xl shadow-md p-4 menu-item-card">
            <img
                src={item.image || "/placeholder.svg"}
                alt={item.name}
                className="w-full h-40 object-cover rounded-xl mb-4"
            />
            <h3 className="font-semibold text-lg mb-1">{item.name}</h3>
            <p className="text-sm text-gray-600 mb-4 line-clamp-2">{item.description}</p>
            <div className="flex items-center justify-between">
                <div className="font-bold text-xl text-primary-color">${item.price.toFixed(1)}</div>
                <div className="flex items-center bg-gray-100 rounded-full">
                    <Button
                        stylingMode="text"
                        onClick={() => onRemove(item.id)}
                        disabled={quantity === 0}
                        text="-"
                        width={40}
                        height={40}
                        className="rounded-full text-xl font-bold"
                    />
                    <span className="mx-2 w-8 text-center font-semibold">{quantity}</span>
                    <Button
                        stylingMode="text"
                        onClick={() => onAdd(item)}
                        text="+"
                        width={40}
                        height={40}
                        className="rounded-full text-xl font-bold"
                    />
                </div>
            </div>
        </div>
    );
}

