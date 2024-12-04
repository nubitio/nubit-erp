import React, {useState} from 'react';
import Splitter, {Item} from 'devextreme-react/splitter';
import {Button} from 'devextreme-react/button';
import './POSPage.scss';
import {POSItem} from "./POSItem";
import type {MenuItem as MenuItemType} from './types';

const categories = [];
const menuData: MenuItemType[] = [
];

const POSPage = () => {
    const [cart, setCart] = useState([] as any[]);
    const [activeCategory, setActiveCategory] = useState('All');

    const addToCart = (item) => {
        setCart((prevCart) => {
            const existingItem = prevCart.find((cartItem) => cartItem.id === item.id);
            if (existingItem) {
                return prevCart.map((cartItem) =>
                    cartItem.id === item.id ? {...cartItem, quantity: cartItem.quantity + 1} : cartItem
                );
            }
            return [...prevCart, {...item, quantity: 1}];
        });
    };

    const removeFromCart = (id) => {
        setCart((prevCart) => prevCart.filter((item) => item.id !== id));
    }

    const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);

    const filteredMenu = activeCategory === 'All'
        ? menuData
        : menuData.filter((item) => item.category === activeCategory);

    return (
        <Splitter id="pos-splitter">
            {/* Menu Section */}
            <Item resizable={true} minSize="65%" size="70%">
                <div className="flex h-screen bg-gray-100">
                    <div className="flex-1 p-6 overflow-auto">
                        <div className="menu-section">
                            {/* Categories */}
                            <div className="categories">
                                {categories.map((category) => (
                                    <Button
                                        key={category}
                                        text={category}
                                        className={`category-button ${activeCategory === category ? 'active' : ''}`}
                                        onClick={() => setActiveCategory(category)}
                                    />
                                ))}
                            </div>

                            {/* Menu Grid */}
                            <div className="grid grid-cols-3 gap-6">
                                {filteredMenu.map((item) => (
                                    <POSItem
                                        key={item.id}
                                        item={item}
                                        onAdd={addToCart}
                                        onRemove={removeFromCart}
                                        quantity={(cart.find((cartItem) => cartItem.id === item.id) || {quantity: 0}).quantity}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </Item>

            {/* Invoice Section */}
            <Item resizable={true} size="30%" minSize="25%" collapsible={true}>
                <div className="invoice-panel">
                    <h3>Invoice</h3>
                    {cart.map((item) => (
                        <div key={item.id} className="invoice-item">
                            <span>{item.name} x {item.quantity}</span>
                            <span>${(item.price * item.quantity).toFixed(2)}</span>
                        </div>
                    ))}
                    <div className="invoice-total">
                        <span>Total:</span>
                        <span>${total.toFixed(2)}</span>
                    </div>
                    <Button text="Place Order" width="100%"/>
                </div>
            </Item>
        </Splitter>
    );
};

export default POSPage;
